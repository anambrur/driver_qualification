<?php

namespace App\Services\Driver;

use App\Features\Feature;
use App\Mail\DriverHiredMail;
use App\Mail\DriverRejectedMail;
use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\User;
use App\Services\Documents\FolderService;
use App\Services\Entitlements\Limits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

class DriverHireService
{
    public function __construct(private FolderService $folders) {}

    public function hire(Driver $driver, array $data, int $actorId): Driver
    {
        $driver = DB::transaction(function () use ($driver, $data, $actorId) {
            $driver = $this->lockPendingDriver($driver);

            // Hiring moves the driver into the counted state (05 §5).
            Limits::assertCanCreate($driver->company_id, Feature::MaxDrivers);

            $driver->update([
                'employment_status' => 'active',
                'application_status' => $driver->application_status === null ? null : 'approved',
                'hire_date' => $data['hire_date'],
                'hazmat' => $data['hazmat'] === 'yes',
                'lcv_certificate' => $data['lcv_certificate'] === 'yes',
                'hired_at' => now(),
                'decided_by_user_id' => $actorId,
                'rejection_reason' => null,
                'rejection_notes' => null,
                'rejected_at' => null,
            ]);
            AuditLog::record(User::query()->findOrFail($actorId), $driver->company_id, 'driver.hired', $driver, ['hire_date' => $data['hire_date']]);
            // A public applicant gets the DQ file folder now; a staff-created driver already has it.
            $this->folders->ensureSystemFolder($driver);

            return $driver->fresh(['company']);
        });

        $this->queueHireEmail($driver);

        return $driver;
    }

    public function reject(Driver $driver, array $data, int $actorId): Driver
    {
        $driver = DB::transaction(function () use ($driver, $data, $actorId) {
            $driver = $this->lockPendingDriver($driver);

            $driver->update([
                'application_status' => 'rejected',
                'rejection_reason' => $data['rejection_reason'],
                'rejection_notes' => $data['additional_info'] ?? null,
                'rejected_at' => now(),
                'decided_by_user_id' => $actorId,
                'hazmat' => false,
                'lcv_certificate' => false,
                'hire_date' => null,
                'hired_at' => null,
            ]);
            AuditLog::record(User::query()->findOrFail($actorId), $driver->company_id, 'driver.rejected', $driver, ['rejection_reason' => $data['rejection_reason']]);

            return $driver->fresh(['company']);
        });

        $this->queueRejectEmail($driver);

        return $driver;
    }

    public function getRejectionReasonLabel(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $labels = [
            'not_good_fit' => 'Applicant is not a good fit for our company',
            'failed_drug_test' => 'Applicant failed a pre-employment drug test',
            'background_check_issues' => 'Items found on the background check',
            'cdl_issues' => 'Items found on the Commercial Driver\'s License',
            'mvr_issues' => 'Items found on the Motor Vehicle Report (MVR)',
            'psp_issues' => 'Items found on the Pre-Employment Screening Program (PSP) report',
            'other' => 'Other reason not listed above',
        ];

        return $labels[$reason] ?? ucfirst($reason);
    }

    public function getStatusLabel(string $status): string
    {
        $labels = [
            'draft' => 'Draft',
            'pending' => 'Pending',
            'active' => 'Active (Hired)',
            'inactive' => 'Inactive',
            'submitted' => 'Submitted',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected (Not Hired)',
        ];

        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Re-read the driver under a row lock so the pending check and the status change are
     * atomic: a double-submit (or a stale model) cannot hire/reject twice or send two emails.
     */
    private function lockPendingDriver(Driver $driver): Driver
    {
        $locked = Driver::query()->whereKey($driver->getKey())->lockForUpdate()->first();

        // Staff-created drivers have no application; public ones must have been submitted.
        if ($locked === null || $locked->employment_status !== 'applicant'
            || ! in_array($locked->application_status, [null, 'pending', 'submitted', 'under_review'], true)) {
            throw new InvalidArgumentException('This driver is not in pending status.');
        }

        return $locked;
    }

    private function queueHireEmail(Driver $driver): void
    {
        if (empty($driver->email)) {
            return;
        }

        try {
            Mail::to($driver->email)->send(new DriverHiredMail(
                driver: $driver,
                companyName: $driver->company?->company_name,
            ));
        } catch (Throwable $e) {
            Log::error('Failed to queue driver hired email.', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function queueRejectEmail(Driver $driver): void
    {
        if (empty($driver->email)) {
            return;
        }

        try {
            Mail::to($driver->email)->send(new DriverRejectedMail(
                driver: $driver,
                reasonLabel: $this->getRejectionReasonLabel($driver->rejection_reason),
                companyName: $driver->company?->company_name,
            ));
        } catch (Throwable $e) {
            Log::error('Failed to queue driver rejected email.', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

<?php

namespace App\Services\Driver;

use App\Mail\DriverHiredMail;
use App\Mail\DriverRejectedMail;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

class DriverHireService
{
    public function hire(Driver $driver, array $data, int $actorId): Driver
    {
        if ($driver->status !== 'pending') {
            throw new InvalidArgumentException('This driver is not in pending status.');
        }

        $driver = DB::transaction(function () use ($driver, $data, $actorId) {
            $driver->update([
                'status' => 'active',
                'hire_date' => $data['hire_date'],
                'hazmat' => $data['hazmat'],
                'lcv_certificate' => $data['lcv_certificate'],
                'hired_at' => now(),
                'action_by' => $actorId,
                'rejection_reason' => null,
                'rejection_notes' => null,
                'rejection_date' => null,
                'rejected_at' => null,
            ]);

            return $driver->fresh(['company']);
        });

        $this->queueHireEmail($driver);

        return $driver;
    }

    public function reject(Driver $driver, array $data, int $actorId): Driver
    {
        if ($driver->status !== 'pending') {
            throw new InvalidArgumentException('This driver is not in pending status.');
        }

        $driver = DB::transaction(function () use ($driver, $data, $actorId) {
            $driver->update([
                'status' => 'rejected',
                'rejection_reason' => $data['rejection_reason'],
                'rejection_notes' => $data['additional_info'] ?? null,
                'rejection_date' => $data['record_date'],
                'rejected_at' => now(),
                'action_by' => $actorId,
                'hazmat' => null,
                'lcv_certificate' => null,
                'hire_date' => null,
                'hired_at' => null,
            ]);

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

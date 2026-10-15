<?php

namespace App\Services\Platform;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PlatformUser;
use App\Services\Company\CompanyProvisioner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Company metadata and lifecycle from the platform (04 §5). Never tenant records: those need a
 * support session.
 */
class PlatformCompanyService
{
    public const STATUSES = ['active', 'suspended', 'deactivated'];

    public function __construct(
        private CompanyProvisioner $provisioner,
        private EmailIdentityService $emails,
    ) {}

    /**
     * The owner sets their own password through the emailed reset link.
     *
     * @param  array<string, mixed>  $profile  companies columns (company_name required)
     */
    public function create(PlatformUser $actor, array $profile, string $ownerName, string $ownerEmail): Company
    {
        $company = $this->emails->claim($ownerEmail, function (string $email) use ($actor, $profile, $ownerName) {
            // The emailed reset link proves the address, as an invitation token does
            $company = $this->provisioner->provision($profile, [
                'name' => $ownerName,
                'email' => $email,
                'password' => Str::random(64),
                'email_verified_at' => now(),
            ]);

            AuditLog::record($actor, $company->id, 'company.created', $company, ['owner_email' => $email] + $profile);

            return $company;
        });

        Password::broker('users')->sendResetLink(['email' => $company->owner->email]);

        return $company;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    public function update(PlatformUser $actor, Company $company, array $profile): void
    {
        $company->fill($profile);
        $changes = $company->getDirty();

        if ($changes === []) {
            return;
        }

        DB::transaction(function () use ($actor, $company, $changes) {
            $company->save();
            AuditLog::record($actor, $company->id, 'company.updated', $company, $changes);
        });
    }

    /**
     * Suspend, reactivate or deactivate (PO-13). A company that is not active logs out all its
     * users on their next request (ResolveTenant); every record is kept.
     */
    public function setStatus(PlatformUser $actor, Company $company, string $status, string $reason): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unknown company status [{$status}].");
        }

        DB::transaction(function () use ($actor, $company, $status, $reason) {
            $locked = Company::query()->lockForUpdate()->findOrFail($company->id);
            $from = $locked->status;

            if ($from === $status) {
                return;
            }

            $locked->forceFill(['status' => $status, 'status_changed_at' => now(), 'status_reason' => $reason])->save();
            AuditLog::record($actor, $locked->id, 'company.status_changed', $locked, ['from' => $from, 'to' => $status, 'reason' => $reason]);
        });
    }
}

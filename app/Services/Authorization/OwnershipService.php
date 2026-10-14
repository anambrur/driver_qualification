<?php

namespace App\Services\Authorization;

use App\Authorization\RoleTemplates;
use App\Authorization\TeamScope;
use App\Authorization\TenantPermissionResolver;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PlatformUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The only way the Owner role moves (06 §8 rule 5). The previous owner keeps access as
 * Administrator while the company still has that role.
 */
class OwnershipService
{
    public function __construct(private TenantPermissionResolver $resolver) {}

    public function transfer(User|PlatformUser $actor, Company $company, int $newOwnerId): Company
    {
        $allowed = $actor instanceof PlatformUser
            ? $this->resolver->has($actor, 'platform.companies.manage')
            : (int) $actor->company_id === (int) $company->id && (int) $company->owner_user_id === (int) $actor->id;

        if (! $allowed) {
            throw new AuthorizationException;
        }

        $newOwner = User::query()
            ->where('company_id', $company->id)
            ->where('user_type', 'member')
            ->findOrFail($newOwnerId);

        if ($newOwner->status !== 'active') {
            throw new AuthorizationException('The new owner must be an active user.');
        }

        return DB::transaction(fn () => TeamScope::run($company->id, function () use ($actor, $company, $newOwner) {
            $locked = Company::query()->lockForUpdate()->findOrFail($company->id);
            $oldOwner = User::query()->findOrFail($locked->owner_user_id);

            if ((int) $oldOwner->id === (int) $newOwner->id
                || ($actor instanceof User && (int) $actor->id !== (int) $oldOwner->id)) {
                throw new AuthorizationException('Ownership changed in the meantime.');
            }

            $owner = $this->roles($company)->where('system_key', 'owner')->firstOrFail();
            $administrator = $this->roles($company)->whereNull('system_key')->where('is_system', true)
                ->where('name', RoleTemplates::ADMINISTRATOR)->first();

            $oldOwner->removeRole($owner);

            if ($administrator !== null) {
                $oldOwner->assignRole($administrator);
            }

            $newOwner->assignRole($owner);
            $locked->forceFill(['owner_user_id' => $newOwner->id])->save();

            $this->resolver->forget($oldOwner);
            $this->resolver->forget($newOwner);

            AuditLog::record($actor, $company->id, 'company.ownership_transferred', $locked, [
                'from_user_id' => $oldOwner->id,
                'to_user_id' => $newOwner->id,
            ]);

            return $locked;
        }));
    }

    /**
     * @return Builder<Role>
     */
    private function roles(Company $company): Builder
    {
        return Role::query()->where('team_id', $company->id)->where('guard_name', 'web');
    }
}

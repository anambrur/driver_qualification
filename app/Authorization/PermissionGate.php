<?php

namespace App\Authorization;

use App\Models\Permission;
use App\Models\PlatformUser;
use App\Models\User;
use App\Services\Billing\AccessState;
use App\Services\Entitlements\Entitlements;
use App\Tenancy\TenantContext;

/**
 * The app's single Gate::before (06 §7). Spatie's own Gate hook is off
 * (register_permission_check_method = false), so the permission: middleware, @can, can() and
 * policies all pass through here first.
 */
class PermissionGate
{
    public function __construct(
        private TenantContext $tenant,
        private TenantPermissionResolver $resolver,
    ) {}

    public function before(mixed $user, string $ability): ?bool
    {
        if (! $user instanceof User && ! $user instanceof PlatformUser) {
            return null;
        }

        // 1-2. Platform staff only outside tenant context; tenant users only inside their own company.
        if ($user instanceof PlatformUser) {
            if (! in_array($this->tenant->mode(), [null, 'platform'], true)) {
                return false;
            }
        } elseif (! in_array($this->tenant->mode(), ['tenant', 'support_session'], true)
            || $this->tenant->companyId() !== (int) $user->company_id) {
            return false;
        }

        // 3. Not a permission: policies decide. A permission of the other guard is refused.
        $permission = Permission::fromCatalogue($ability, $this->resolver->guardOf($user));

        if ($permission === null) {
            return Permission::fromCatalogue($ability) === null ? null : false;
        }

        // 4. Entitlement (05 §4): a feature the company is not entitled to denies; a read-only
        //    one (lapsed or lost through a plan change) keeps viewing and exporting.
        if ($permission->feature_key !== null && $user instanceof User && $user->company) {
            $state = Entitlements::for($user->company)->state($permission->feature_key);

            if (! $state->allowsRead() || ($permission->is_write && ! $state->allowsWrite())) {
                return false;
            }
        }

        // 5. A read-only company keeps viewing and exporting; billing stays open so it can pay (07 §4.3).
        if ($permission->is_write && ! str_starts_with($permission->name, 'billing.')
            && $user instanceof User && $user->company
            && ! AccessState::for($user->company)->allowsWrites()) {
            return false;
        }

        return $this->resolver->has($user, $permission->name);
    }
}

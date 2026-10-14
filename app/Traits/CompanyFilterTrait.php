<?php

namespace App\Traits;

use App\Models\Company;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Interim helpers for the company of new records and the company dropdowns. Row filtering and
 * the access check moved to the tenant scope and policies (WP5); the super-admin branches go
 * with WP8 (03 §7).
 */
trait CompanyFilterTrait
{
    /**
     * Super-admins are not filtered. Interim until the platform area replaces them (03 §7).
     */
    protected function isSuperAdmin(): bool
    {
        return Auth::user()?->hasRole('super-admin') ?? false;
    }

    /**
     * The tenant of this request, from TenantContext (set by ResolveTenant).
     */
    protected function tenantCompanyId(): ?int
    {
        return app(TenantContext::class)->companyId();
    }

    /**
     * Get the company ID for the authenticated user.
     * Super-admin returns null (no filtering).
     */
    protected function getUserCompanyId(): ?int
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        return $this->tenantCompanyId();
    }

    /**
     * Get the company ID for the authenticated user (including super-admin's owned company).
     * Used when a company must be selected/forced on create.
     */
    protected function getAllUserCompanyId(): ?int
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $user->loadMissing('company');

        return $user->company?->id;
    }

    /**
     * Company a new record belongs to. Tenants always get their own company; a super-admin
     * acting on another company's resource (e.g. its vehicle) gets that resource's company,
     * and falls back to their own company when there is none.
     */
    protected function getOwningCompanyId(?int $resourceCompanyId): ?int
    {
        if ($this->isSuperAdmin()) {
            return $resourceCompanyId ?? $this->getAllUserCompanyId();
        }

        return $this->tenantCompanyId();
    }

    /**
     * Get companies for dropdown based on user role.
     *
     * @return \Illuminate\Database\Eloquent\Collection|Collection
     */
    protected function getCompaniesForUser()
    {
        if ($this->isSuperAdmin()) {
            return Company::where('status', 'active')->get();
        }

        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        $user->loadMissing('company');

        return $user->company ? collect([$user->company]) : collect();
    }
}

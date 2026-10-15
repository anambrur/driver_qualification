<?php

namespace App\Traits;

use App\Models\Company;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Interim helpers for the company of new records and the company dropdowns. Row filtering and
 * the access check moved to the tenant scope and policies (WP5). Tenant code only ever works
 * in the request's own company; the platform works through /platform (03 §7).
 */
trait CompanyFilterTrait
{
    /**
     * The tenant of this request, from TenantContext (set by ResolveTenant).
     */
    protected function tenantCompanyId(): ?int
    {
        return app(TenantContext::class)->companyId();
    }

    /**
     * Get the company ID for the authenticated user.
     */
    protected function getUserCompanyId(): ?int
    {
        return $this->tenantCompanyId();
    }

    /**
     * Get the company ID for the authenticated user.
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
     * The user's own company, for the company dropdowns.
     *
     * @return Collection<int, Company>
     */
    protected function getCompaniesForUser(): Collection
    {
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        $user->loadMissing('company');

        return $user->company ? collect([$user->company]) : collect();
    }
}

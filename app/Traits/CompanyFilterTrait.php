<?php

namespace App\Traits;

use App\Models\Company;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
     * Apply company filter to a query.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyCompanyFilter($query, string $companyColumn = 'company_id')
    {
        if ($this->isSuperAdmin()) {
            return $query;
        }

        $companyId = $this->tenantCompanyId();

        if ($companyId !== null) {
            return $query->where($companyColumn, $companyId);
        }

        // Non-super-admin with no company assigned should see no records.
        return $query->where($companyColumn, -1);
    }

    /**
     * Check if user has access to a specific company resource.
     *
     * @param  mixed  $resource
     */
    protected function userHasAccess($resource, string $companyColumn = 'company_id'): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $companyId = $this->tenantCompanyId();

        if ($companyId === null) {
            return false;
        }

        return (int) $resource->$companyColumn === $companyId;
    }

    /**
     * Authorize user action on a resource.
     *
     * @param  mixed  $resource
     *
     * @throws AccessDeniedHttpException
     */
    protected function authorizeCompanyAccess($resource, string $message = 'Unauthorized access.', string $companyColumn = 'company_id'): void
    {
        if (! $this->userHasAccess($resource, $companyColumn)) {
            abort(403, $message);
        }
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

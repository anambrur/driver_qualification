<?php

namespace App\Traits;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;

trait CompanyFilterTrait
{
    protected bool $companyContextResolved = false;

    protected ?bool $resolvedSuperAdmin = null;

    protected ?int $resolvedCompanyId = null;

    /**
     * Resolve and memoize company context once per request/controller instance.
     */
    protected function resolveCompanyContext(): void
    {
        if ($this->companyContextResolved) {
            return;
        }

        $user = Auth::user();
        $this->resolvedSuperAdmin = $user?->hasRole('super-admin') ?? false;

        if (! $this->resolvedSuperAdmin && $user) {
            $user->loadMissing('company');
            $this->resolvedCompanyId = $user->company?->id;
        }

        $this->companyContextResolved = true;
    }

    /**
     * Get the company ID for the authenticated user.
     * Super-admin returns null (no filtering).
     */
    protected function getUserCompanyId(): ?int
    {
        $this->resolveCompanyContext();

        if ($this->resolvedSuperAdmin) {
            return null;
        }

        return $this->resolvedCompanyId;
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
     * Apply company filter to a query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $companyColumn
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyCompanyFilter($query, string $companyColumn = 'company_id')
    {
        $this->resolveCompanyContext();

        if ($this->resolvedSuperAdmin) {
            return $query;
        }

        if ($this->resolvedCompanyId !== null) {
            return $query->where($companyColumn, $this->resolvedCompanyId);
        }

        // Non-super-admin with no company assigned should see no records.
        return $query->where($companyColumn, -1);
    }

    /**
     * Check if user has access to a specific company resource.
     *
     * @param  mixed  $resource
     * @param  string  $companyColumn
     */
    protected function userHasAccess($resource, string $companyColumn = 'company_id'): bool
    {
        $this->resolveCompanyContext();

        if ($this->resolvedSuperAdmin) {
            return true;
        }

        if ($this->resolvedCompanyId === null) {
            return false;
        }

        return (int) $resource->$companyColumn === $this->resolvedCompanyId;
    }

    /**
     * Authorize user action on a resource.
     *
     * @param  mixed  $resource
     * @param  string  $message
     * @param  string  $companyColumn
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
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
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */
    protected function getCompaniesForUser()
    {
        $this->resolveCompanyContext();

        if ($this->resolvedSuperAdmin) {
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

<?php

namespace App\Traits;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;

trait CompanyFilterTrait
{
    /**
     * Get the company ID(s) for the authenticated user
     * 
     * @return int|array|null
     */
    protected function getUserCompanyId()
    {
        $user = Auth::user();

        // Super admin can access all companies
        if ($user->hasRole('super-admin')) {
            return null; // null means no filtering
        }

        // Get user's company
        $company = $user->load('company')->company;

        return $company ? $company->id : null;
    }

     /**
     * Get the company ID(s) for the authenticated user
     * 
     * @return int|array|null
     */
    protected function getAllUserCompanyId()
    {
        $user = Auth::user();

        // Get user's company
        $company = $user->load('company')->company;

        return $company ? $company->id : null;
    }

    /**
     * Apply company filter to a query
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $companyColumn
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyCompanyFilter($query, $companyColumn = 'company_id')
    {
        $companyId = $this->getUserCompanyId();

        if ($companyId !== null) {
            return $query->where($companyColumn, $companyId);
        }

        return $query;
    }

    /**
     * Check if user has access to a specific company resource
     * 
     * @param mixed $resource
     * @param string $companyColumn
     * @return bool
     */
    protected function userHasAccess($resource, $companyColumn = 'company_id')
    {
        $user = Auth::user();

        // Super admin has access to everything
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $companyId = $this->getUserCompanyId();

        // If user has no company, they can't access any company-specific resources
        if (!$companyId) {
            return false;
        }

        return $resource->$companyColumn == $companyId;
    }

    /**
     * Authorize user action on a resource
     * 
     * @param mixed $resource
     * @param string $message
     * @param string $companyColumn
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    protected function authorizeCompanyAccess($resource, $message = 'Unauthorized access.', $companyColumn = 'company_id')
    {
        if (!$this->userHasAccess($resource, $companyColumn)) {
            abort(403, $message);
        }
    }

    /**
     * Get companies for dropdown based on user role
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getCompaniesForUser()
    {
        $user = Auth::user();

        if ($user->hasRole('super-admin')) {
            return Company::where('status', 'active')->get();
        }

        $company = $user->load('company')->company;

        return $company ? collect([$company]) : collect();
    }
}
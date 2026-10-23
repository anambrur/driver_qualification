<?php

namespace App\Http\Middleware;

use App\Features\Feature;
use App\Models\Company;
use App\Services\Entitlements\Entitlements;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starting or continuing a public application needs driver_applications on (Technical-Features
 * §3.2). Entitlements already turn it off or read-only for a blocked or read-only company. Runs
 * after ResolvePublicCompany; the status, file and withdraw routes stay open for past applicants.
 */
class EnsureApplicationsOpen
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = Company::query()->findOrFail(app(TenantContext::class)->requireCompanyId());

        if (! Entitlements::for($company)->state(Feature::DriverApplications)->allowsWrite()) {
            return response()->view('application.closed', ['company' => $company], 403);
        }

        return $next($request);
    }
}

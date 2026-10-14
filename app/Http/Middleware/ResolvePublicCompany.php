<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public application routes (/{slug}/...): the slug's active company becomes the tenant, in
 * public_applicant mode (03 §4.1). An unknown or inactive company is a 404.
 */
class ResolvePublicCompany
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = Company::query()
            ->where('slug', $request->route('slug'))
            ->where('status', 'active')
            ->value('id');

        abort_if($companyId === null, 404);

        return app(TenantContext::class)->run((int) $companyId, fn () => $next($request), 'public_applicant');
    }
}

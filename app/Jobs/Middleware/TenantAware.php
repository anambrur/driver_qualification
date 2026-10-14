<?php

namespace App\Jobs\Middleware;

use App\Models\Company;
use App\Tenancy\TenantAwareJob;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Runs a tenant job inside its company's context and Spatie team, and resets both afterwards
 * so nothing leaks to the next job in the same worker (03 §4.6). Jobs of a company that is not
 * active are dropped.
 */
class TenantAware
{
    public function handle(TenantAwareJob $job, Closure $next): mixed
    {
        $companyId = $job->companyId();
        $status = Company::query()->whereKey($companyId)->value('status');

        if ($status !== 'active') {
            Log::info('Tenant job dropped: company is not active', [
                'job' => $job::class,
                'company_id' => $companyId,
                'status' => $status,
            ]);

            if (method_exists($job, 'delete')) {
                $job->delete();
            }

            return null;
        }

        return app(TenantContext::class)->run($companyId, fn () => $next($job));
    }
}

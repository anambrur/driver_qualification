<?php

namespace App\Jobs\Middleware;

use App\Authorization\TenantPermissionResolver;
use App\Models\Company;
use App\Services\Entitlements\Entitlements;
use App\Tenancy\TenantAwareJob;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Runs a tenant job inside its company's context and Spatie team, and resets both afterwards
 * so nothing leaks to the next job in the same worker (03 §4.6). Jobs of a company that is not
 * active are dropped, and so are jobs whose declared feature the company no longer has
 * (`public string $feature`, 05 §4); a read-only feature runs only jobs that declare
 * `public bool $readOnly = true`. The permission memo is cleared before and after (06 §9).
 */
class TenantAware
{
    public function handle(TenantAwareJob $job, Closure $next): mixed
    {
        $companyId = $job->companyId();
        $company = Company::query()->find($companyId);

        if ($company?->status !== 'active') {
            return $this->drop($job, 'company is not active', ['status' => $company?->status]);
        }

        if (property_exists($job, 'feature')) {
            $state = Entitlements::for($company)->state($job->feature);
            $readOnlyJob = property_exists($job, 'readOnly') && $job->readOnly === true;

            if (! $state->allowsRead() || (! $state->allowsWrite() && ! $readOnlyJob)) {
                return $this->drop($job, 'feature not entitled', ['feature' => $job->feature, 'state' => $state->value]);
            }
        }

        $resolver = app(TenantPermissionResolver::class);
        $resolver->flush();

        try {
            return app(TenantContext::class)->run($companyId, fn () => $next($job));
        } finally {
            $resolver->flush();
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function drop(TenantAwareJob $job, string $reason, array $context): null
    {
        Log::info("Tenant job dropped: {$reason}", ['job' => $job::class, 'company_id' => $job->companyId()] + $context);

        if (method_exists($job, 'delete')) {
            $job->delete();
        }

        return null;
    }
}

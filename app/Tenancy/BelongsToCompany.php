<?php

namespace App\Tenancy;

use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

/**
 * Tenant model (03 §4.2): scoped to the current company and filled from it on create.
 * Not for User: login and password reset look users up across companies.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model) {
            $companyId = app(TenantContext::class)->companyId()
                ?? throw new MissingTenantContextException('Creating tenant model ['.$model::class.'] without a tenant context.');

            if ($model->getAttribute('company_id') === null) {
                $model->setAttribute('company_id', $companyId);
            } elseif ((int) $model->getAttribute('company_id') !== $companyId) {
                throw new CrossTenantWriteException('Tenant model ['.$model::class.'] belongs to another company than the tenant context.');
            }
        });
    }

    /**
     * Platform-wide query without the company scope. Every caller is logged.
     *
     * @return Builder<static>
     */
    public static function withoutTenancy(): Builder
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
        Log::info('Tenant scope bypassed', [
            'model' => static::class,
            'caller' => ($caller['class'] ?? '').'::'.($caller['function'] ?? ''),
        ]);

        return static::query()->withoutGlobalScope(CompanyScope::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

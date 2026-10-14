<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Fails closed: without a tenant context the query throws instead of returning every company.
 *
 * @implements Scope<Model>
 */
class CompanyScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(TenantContext::class)->companyId()
            ?? throw new MissingTenantContextException(
                'Query on tenant model ['.$model::class.'] without a tenant context. Use TenantContext::run() or '.$model::class.'::withoutTenancy().'
            );

        $builder->where($model->qualifyColumn('company_id'), $companyId);
    }
}

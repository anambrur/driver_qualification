<?php

namespace Database\Factories\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;

/**
 * For factories of BelongsToCompany models: each model is saved inside its own company's
 * tenant context (03 §6), so tests and seeders can create rows of any company.
 */
trait CreatesInTenant
{
    protected function store(Collection $results)
    {
        $results->each(fn ($model) => $model->company_id === null
            ? parent::store(collect([$model]))
            : app(TenantContext::class)->run((int) $model->company_id, fn () => parent::store(collect([$model]))));
    }
}

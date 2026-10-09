<?php

namespace App\Http\Requests\PublicApplication;

use App\Models\Company;
use Illuminate\Validation\Rule;

/**
 * driver_id must belong to the company in the {slug} route parameter.
 */
trait ValidatesApplicationDriver
{
    /**
     * @return array<int, mixed>
     */
    protected function applicationDriverRules(): array
    {
        $companyId = Company::where('slug', (string) $this->route('slug'))->value('id');

        return ['required', Rule::exists('drivers', 'id')->where('company_id', $companyId ?? 0)];
    }
}

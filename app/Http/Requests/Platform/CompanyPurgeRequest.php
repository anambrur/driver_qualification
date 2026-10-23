<?php

namespace App\Http\Requests\Platform;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The purge is confirmed by typing the company's exact name, with a reason for the audit log.
 */
class CompanyPurgeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Company $company */
        $company = $this->route('company');

        return [
            'confirm_name' => ['required', 'string', Rule::in([$company->company_name])],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirm_name.in' => 'Type the company name exactly as shown.'];
    }
}

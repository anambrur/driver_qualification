<?php

namespace App\Http\Requests\PublicApplication;

use App\Http\Requests\Driver\StoreDriverRequest;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationStep1Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = StoreDriverRequest::step1FieldRules(true, true);

        // drivers.email is unique per company; say so instead of failing on insert.
        $companyId = Company::where('slug', (string) $this->route('slug'))->value('id');
        $rules['email'] = [
            'required', 'email', 'max:255',
            Rule::unique('drivers', 'email')
                ->where('company_id', $companyId ?? 0)
                ->ignore($this->session()->get('application_driver_id')),
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'Driver must be at least 18 years old.',
            'repeat_license_number.same' => 'License numbers do not match.',
            'license_expires.after' => 'License expiration date must be after the issued date.',
            'email.unique' => 'This email is already used by another application at this company.',
        ];
    }
}

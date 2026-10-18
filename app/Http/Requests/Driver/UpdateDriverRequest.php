<?php

namespace App\Http\Requests\Driver;

use App\Models\Driver;
use App\Rules\PlaceCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    /**
     * Another company's driver is a 404 before validation runs (03 §5).
     */
    public function authorize(): bool
    {
        Gate::authorize('update', Driver::findOrFail($this->route('id')));

        return true;
    }

    protected function prepareForValidation(): void
    {
        // Always the user's own company; a posted company_id is ignored
        $user = $this->user();
        $user?->loadMissing('company');
        if ($user?->company) {
            $this->merge(['company_id' => $user->company->id]);
        }

        $this->merge(StoreDriverRequest::paddedRows($this->all()));
    }

    public function rules(): array
    {
        $user = $this->user();
        $user?->loadMissing('company');
        $companyId = $user?->company?->id;
        $companyRule = ['required', Rule::in($companyId ? [$companyId] : [-1])];

        // No `status` rule: the edit form must not change the status. Hiring and rejecting go
        // through admin.driver.hire-status, which checks, audits and emails (DRV-06).
        return array_merge(
            StoreDriverRequest::step1FieldRules(requireLicense: false),
            [
                'company_id' => $companyRule,
                'email' => StoreDriverRequest::emailRule($this->input('company_id'), $this->route('id')),
                'state' => ['required', 'string', 'max:255', PlaceCode::state()],
                'country' => ['required', 'string', 'max:255', PlaceCode::country()],
                'twic_card' => 'boolean',
                'passport' => 'boolean',
            ]
        );
    }

    public function attributes(): array
    {
        return StoreDriverRequest::rowAttributes();
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'Driver must be at least 18 years old.',
            'repeat_license_number.same' => 'License numbers do not match.',
            'license_expires.after' => 'License expiration date must be after the issued date.',
            'company_id.in' => 'You can only update drivers for your own company.',
            'email.unique' => 'This email is already used by another driver at this company, including deleted drivers.',
        ];
    }
}

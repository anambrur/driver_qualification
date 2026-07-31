<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();
        if ($user && ! $user->hasRole('super-admin')) {
            $user->loadMissing('company');
            if ($user->company) {
                $this->merge(['company_id' => $user->company->id]);
            }
        }
    }

    public function rules(): array
    {
        $user = $this->user();
        $companyRule = ['required', 'exists:companies,id'];

        if ($user && ! $user->hasRole('super-admin')) {
            $user->loadMissing('company');
            $companyId = $user->company?->id;
            $companyRule = ['required', Rule::in($companyId ? [$companyId] : [-1])];
        }

        return array_merge(
            StoreDriverRequest::step1FieldRules(requireMedicalFuture: false, requireLicense: false),
            [
                'company_id' => $companyRule,
                'status' => 'required|in:draft,pending,active,inactive,submitted,under_review,approved,rejected',
                'state' => 'required|string|max:255',
                'country' => 'required|string|max:255',
                'twic_card' => 'boolean',
                'passport' => 'boolean',
            ]
        );
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'Driver must be at least 18 years old.',
            'repeat_license_number.same' => 'License numbers do not match.',
            'license_expires.after' => 'License expiration date must be after the issued date.',
            'company_id.in' => 'You can only update drivers for your own company.',
        ];
    }
}

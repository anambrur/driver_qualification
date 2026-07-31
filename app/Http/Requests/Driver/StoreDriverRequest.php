<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriverRequest extends FormRequest
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
            [
                'company_id' => $companyRule,
            ],
            self::step1FieldRules(requireMedicalFuture: true, requireLicense: true)
        );
    }

    /**
     * Shared step-1 field rules used by admin create and public application step 1.
     */
    public static function step1FieldRules(bool $requireMedicalFuture = true, bool $requireLicense = true): array
    {
        $medical = $requireMedicalFuture
            ? 'required|date|after_or_equal:' . now()->format('Y-m-d')
            : 'nullable|date';

        $license = $requireLicense ? [
            'license_first_name' => 'required|string|max:255',
            'license_last_name' => 'required|string|max:255',
            'license_issued' => 'required|date',
            'license_expires' => 'required|date|after:license_issued',
            'license_country' => 'required',
            'license_state' => 'required',
            'license_class' => 'required|string|max:255',
            'license_number' => 'required|string|max:255',
            'repeat_license_number' => 'required|same:license_number',
            'is_h_placarded_hazmat' => 'sometimes|boolean',
            'is_n_tank_vehicle' => 'sometimes|boolean',
            'is_p_passengers' => 'sometimes|boolean',
            'is_t_double_trailer' => 'sometimes|boolean',
            'is_s_school_bus' => 'sometimes|boolean',
            'is_x_placarded_hazmat' => 'sometimes|boolean',
            'equipment_class' => 'required|array',
            'equipment_class.*' => 'required|string',
            'experience' => 'required|array',
            'experience.*' => 'required|in:no,yes',
        ] : [
            'license_first_name' => 'nullable|string|max:255',
            'license_last_name' => 'nullable|string|max:255',
            'license_issued' => 'nullable|date',
            'license_expires' => 'nullable|date|after:license_issued',
            'license_country' => 'nullable',
            'license_state' => 'nullable',
            'license_number' => 'nullable|string|max:255',
            'repeat_license_number' => 'nullable|string|max:255|same:license_number',
            'license_class' => 'nullable|string|max:255',
            'is_h_placarded_hazmat' => 'boolean',
            'is_n_tank_vehicle' => 'boolean',
            'is_p_passengers' => 'boolean',
            'is_t_double_trailer' => 'boolean',
            'is_s_school_bus' => 'boolean',
            'is_x_placarded_hazmat' => 'boolean',
            'equipment_class' => 'sometimes|array',
            'equipment_class.*' => 'nullable|string',
            'experience' => 'sometimes|array',
            'experience.*' => 'nullable|in:no,yes',
        ];

        return array_merge([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:10',
            'date_of_birth' => 'required|date|before:-18 years',
            'ssn' => 'required|string|max:11',
            'main_phone' => 'required|string|max:20',
            'alt_phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'medical_certificate_expiration_date' => $medical,
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'business_name' => 'nullable|string|max:255',
            'employer_identification_number' => 'nullable|string|max:20',
            'federal_tax_classification' => 'nullable|in:individual_sole_proprietor,c_corporation,s_corporation,llc',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'state' => 'required',
            'country' => 'required',
            'postal_code' => 'required|string|max:20',
            'twic_card' => 'sometimes|boolean',
            'passport' => 'sometimes|boolean',

            'residence_address' => 'sometimes|array',
            'residence_address.*' => 'nullable|string',
            'residence_city' => 'sometimes|array',
            'residence_city.*' => 'nullable|string',
            'residence_country' => 'sometimes|array',
            'residence_country.*' => 'nullable',
            'residence_state' => 'sometimes|array',
            'residence_state.*' => 'nullable',
            'residence_postal_code' => 'sometimes|array',
            'residence_postal_code.*' => 'nullable|string',

            'experience_from_date' => 'sometimes|array',
            'experience_from_date.*' => 'nullable|date',
            'experience_to_date' => 'sometimes|array',
            'experience_to_date.*' => 'nullable|date',
            'approx_miles' => 'sometimes|array',
            'approx_miles.*' => 'nullable|string',

            'accident' => 'required|in:no,yes',
            'accident_date' => 'sometimes|array',
            'accident_date.*' => 'nullable|date',
            'accident_location' => 'sometimes|array',
            'accident_location.*' => 'nullable|string',
            'number_of_injuries' => 'sometimes|array',
            'number_of_injuries.*' => 'nullable|string',
            'number_of_fatalities' => 'sometimes|array',
            'number_of_fatalities.*' => 'nullable|string',
            'hazmat_spill' => 'sometimes|array',
            'hazmat_spill.*' => 'nullable|in:no,yes',

            'violation' => 'required|in:no,yes',
            'violation_date' => 'sometimes|array',
            'violation_date.*' => 'nullable|date',
            'violation_location' => 'sometimes|array',
            'violation_location.*' => 'nullable|string',
            'offense' => 'sometimes|array',
            'offense.*' => 'nullable|string',
            'vehicle_type' => 'sometimes|array',
            'vehicle_type.*' => 'nullable|string',

            'denied_license' => 'required|in:no,yes',
            'license_revoked' => 'required|in:no,yes',
            'forfeitures' => 'nullable|string|max:1000',

            'employer_name' => 'sometimes|array',
            'employer_name.*' => 'nullable|string',
            'employer_record_address' => 'sometimes|array',
            'employer_record_address.*' => 'nullable|string',
            'employer_record_city' => 'sometimes|array',
            'employer_record_city.*' => 'nullable|string',
            'employer_record_country' => 'sometimes|array',
            'employer_record_country.*' => 'nullable',
            'employer_record_state' => 'sometimes|array',
            'employer_record_state.*' => 'nullable',
            'employer_record_postal_code' => 'sometimes|array',
            'employer_record_postal_code.*' => 'nullable|string',
            'employer_record_phone' => 'sometimes|array',
            'employer_record_phone.*' => 'nullable|string',
            'employer_record_fax' => 'sometimes|array',
            'employer_record_fax.*' => 'nullable|string',
            'employer_record_email' => 'sometimes|array',
            'employer_record_email.*' => 'nullable|email',
            'employer_record_position' => 'sometimes|array',
            'employer_record_position.*' => 'nullable|string',
            'employer_record_date_from' => 'sometimes|array',
            'employer_record_date_from.*' => 'nullable|date',
            'employer_record_date_to' => 'sometimes|array',
            'employer_record_date_to.*' => 'nullable|date',
            'employer_record_reason_for_leaving' => 'sometimes|array',
            'employer_record_reason_for_leaving.*' => 'nullable|string',
            'employed_regulations' => 'sometimes|array',
            'employed_regulations.*' => 'nullable|in:no,yes',
            'safety_sensitive_function' => 'sometimes|array',
            'safety_sensitive_function.*' => 'nullable|in:no,yes',
        ], $license);
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'Driver must be at least 18 years old.',
            'repeat_license_number.same' => 'License numbers do not match.',
            'license_expires.after' => 'License expiration date must be after the issued date.',
            'company_id.in' => 'You can only create drivers for your own company.',
        ];
    }
}

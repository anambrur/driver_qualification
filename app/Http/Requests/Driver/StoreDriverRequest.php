<?php

namespace App\Http\Requests\Driver;

use App\Rules\NotBeforeInRow;
use App\Rules\PlaceCode;
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
        // Always the user's own company; a posted company_id is ignored
        $user = $this->user();
        $user?->loadMissing('company');
        if ($user?->company) {
            $this->merge(['company_id' => $user->company->id]);
        }

        $this->merge(self::paddedRows($this->all()));
    }

    public function rules(): array
    {
        $user = $this->user();
        $user?->loadMissing('company');
        $companyId = $user?->company?->id;
        $companyRule = ['required', Rule::in($companyId ? [$companyId] : [-1])];

        return array_merge(
            self::step1FieldRules(requireLicense: true),
            [
                'company_id' => $companyRule,
                'email' => self::emailRule($this->input('company_id')),
            ]
        );
    }

    /**
     * Every column of a posted residence row is present (null when left out), so the
     * `required_with:residence_address.*` rules see a missing city or state too.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function paddedRows(array $input): array
    {
        $columns = ['residence_city', 'residence_country', 'residence_state', 'residence_postal_code'];
        $padded = [];

        foreach (array_keys((array) ($input['residence_address'] ?? [])) as $index) {
            foreach ($columns as $column) {
                $padded[$column] = (array) ($padded[$column] ?? $input[$column] ?? []);
                $padded[$column][$index] ??= null;
            }
        }

        return $padded;
    }

    /**
     * drivers.email is unique per company, and deleted (soft-deleted) drivers keep theirs, so
     * say so instead of failing on insert.
     */
    public static function emailRule($companyId, $ignoreDriverId = null): array
    {
        return [
            'required', 'email', 'max:255',
            Rule::unique('drivers', 'email')->where('company_id', $companyId ?? 0)->ignore($ignoreDriverId),
        ];
    }

    /**
     * Shared step-1 field rules used by admin create and public application step 1.
     */
    public static function step1FieldRules(bool $requireLicense = true): array
    {
        $license = $requireLicense ? [
            'license_first_name' => 'required|string|max:255',
            'license_last_name' => 'required|string|max:255',
            'license_issued' => 'required|date',
            'license_expires' => 'required|date|after:license_issued',
            'license_country' => ['required', PlaceCode::country()],
            'license_state' => ['required', PlaceCode::state()],
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
            'equipment_class.*' => 'required|string|max:255',
            'experience' => 'required|array',
            'experience.*' => 'required|in:no,yes',
        ] : [
            'license_first_name' => 'nullable|required_with:license_number|string|max:255',
            'license_last_name' => 'nullable|required_with:license_number|string|max:255',
            'license_issued' => 'nullable|required_with:license_number|date',
            'license_expires' => 'nullable|required_with:license_number|date|after:license_issued',
            'license_country' => ['nullable', 'required_with:license_number', PlaceCode::country()],
            'license_state' => ['nullable', 'required_with:license_number', PlaceCode::state()],
            'license_number' => 'nullable|string|max:255',
            'repeat_license_number' => 'nullable|string|max:255|same:license_number',
            'license_class' => 'nullable|required_with:license_number|string|max:255',
            'is_h_placarded_hazmat' => 'boolean',
            'is_n_tank_vehicle' => 'boolean',
            'is_p_passengers' => 'boolean',
            'is_t_double_trailer' => 'boolean',
            'is_s_school_bus' => 'boolean',
            'is_x_placarded_hazmat' => 'boolean',
            'equipment_class' => 'sometimes|array',
            'equipment_class.*' => 'nullable|string|max:255',
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
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'business_name' => 'nullable|string|max:255',
            'employer_identification_number' => 'nullable|string|max:20',
            'federal_tax_classification' => 'nullable|in:individual_sole_proprietor,c_corporation,s_corporation,llc',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:255',
            'state' => ['required', PlaceCode::state()],
            'country' => ['required', PlaceCode::country()],
            'postal_code' => 'required|string|max:20',
            'twic_card' => 'sometimes|boolean',
            'passport' => 'sometimes|boolean',

            'residence_address' => 'sometimes|array',
            'residence_address.*' => 'nullable|string|max:255',
            'residence_city' => 'sometimes|array',
            'residence_city.*' => 'nullable|required_with:residence_address.*|string|max:255',
            'residence_country' => 'sometimes|array',
            'residence_country.*' => ['nullable', 'required_with:residence_address.*', PlaceCode::country()],
            'residence_state' => 'sometimes|array',
            'residence_state.*' => ['nullable', 'required_with:residence_address.*', PlaceCode::state()],
            'residence_postal_code' => 'sometimes|array',
            'residence_postal_code.*' => 'nullable|required_with:residence_address.*|string|max:255',

            'experience_from_date' => 'sometimes|array',
            'experience_from_date.*' => 'nullable|date',
            'experience_to_date' => 'sometimes|array',
            'experience_to_date.*' => ['nullable', 'date', new NotBeforeInRow('experience_from_date', 'experience start date')],
            'approx_miles' => 'sometimes|array',
            'approx_miles.*' => 'nullable|integer|min:0|max:4294967295',

            'accident' => 'required|in:no,yes',
            'accident_date' => 'sometimes|array',
            'accident_date.*' => 'nullable|date',
            'accident_location' => 'sometimes|array',
            'accident_location.*' => 'nullable|string|max:255',
            'number_of_injuries' => 'sometimes|array',
            'number_of_injuries.*' => 'nullable|integer|min:0|max:65535',
            'number_of_fatalities' => 'sometimes|array',
            'number_of_fatalities.*' => 'nullable|integer|min:0|max:65535',
            'hazmat_spill' => 'sometimes|array',
            'hazmat_spill.*' => 'nullable|in:no,yes',

            'violation' => 'required|in:no,yes',
            'violation_date' => 'sometimes|array',
            'violation_date.*' => 'nullable|date',
            'violation_location' => 'sometimes|array',
            'violation_location.*' => 'nullable|string|max:255',
            'offense' => 'sometimes|array',
            'offense.*' => 'nullable|string|max:255',
            'vehicle_type' => 'sometimes|array',
            'vehicle_type.*' => 'nullable|string|max:255',

            'denied_license' => 'required|in:no,yes',
            'license_revoked' => 'required|in:no,yes',
            'forfeitures' => 'nullable|string|max:1000',

            'employer_name' => 'sometimes|array',
            'employer_name.*' => 'nullable|string|max:255',
            'employer_record_address' => 'sometimes|array',
            'employer_record_address.*' => 'nullable|string|max:255',
            'employer_record_city' => 'sometimes|array',
            'employer_record_city.*' => 'nullable|string|max:255',
            'employer_record_country' => 'sometimes|array',
            'employer_record_country.*' => ['nullable', PlaceCode::country()],
            'employer_record_state' => 'sometimes|array',
            'employer_record_state.*' => ['nullable', PlaceCode::state()],
            'employer_record_postal_code' => 'sometimes|array',
            'employer_record_postal_code.*' => 'nullable|string|max:255',
            'employer_record_phone' => 'sometimes|array',
            'employer_record_phone.*' => 'nullable|string|max:255',
            'employer_record_fax' => 'sometimes|array',
            'employer_record_fax.*' => 'nullable|string|max:255',
            'employer_record_email' => 'sometimes|array',
            'employer_record_email.*' => 'nullable|email|max:255',
            'employer_record_position' => 'sometimes|array',
            'employer_record_position.*' => 'nullable|string|max:255',
            'employer_record_date_from' => 'sometimes|array',
            'employer_record_date_from.*' => 'nullable|date',
            'employer_record_date_to' => 'sometimes|array',
            'employer_record_date_to.*' => ['nullable', 'date', new NotBeforeInRow('employer_record_date_from', 'employment start date')],
            'employer_record_reason_for_leaving' => 'sometimes|array',
            'employer_record_reason_for_leaving.*' => 'nullable|string|max:5000',
            'employed_regulations' => 'sometimes|array',
            'employed_regulations.*' => 'nullable|in:no,yes',
            'safety_sensitive_function' => 'sometimes|array',
            'safety_sensitive_function.*' => 'nullable|in:no,yes',
        ], $license);
    }

    /**
     * Readable names for the repeating fields (`approx_miles.2` reads as "approximate miles").
     *
     * @return array<string, string>
     */
    public static function rowAttributes(): array
    {
        return [
            'residence_address.*' => 'residence address',
            'residence_city.*' => 'residence city',
            'residence_country.*' => 'residence country',
            'residence_state.*' => 'residence state',
            'residence_postal_code.*' => 'residence postal code',
            'employer_record_country.*' => 'employer country',
            'employer_record_state.*' => 'employer state',
            'approx_miles.*' => 'approximate miles',
            'experience_from_date.*' => 'experience start date',
            'experience_to_date.*' => 'experience end date',
            'number_of_injuries.*' => 'number of injuries',
            'number_of_fatalities.*' => 'number of fatalities',
            'accident_date.*' => 'accident date',
            'violation_date.*' => 'violation date',
            'employer_record_email.*' => 'employer email',
            'employer_record_date_from.*' => 'employment start date',
            'employer_record_date_to.*' => 'employment end date',
        ];
    }

    public function attributes(): array
    {
        return self::rowAttributes();
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before' => 'Driver must be at least 18 years old.',
            'repeat_license_number.same' => 'License numbers do not match.',
            'license_expires.after' => 'License expiration date must be after the issued date.',
            'company_id.in' => 'You can only create drivers for your own company.',
            'email.unique' => 'This email is already used by another driver at this company, including deleted drivers.',
        ];
    }
}

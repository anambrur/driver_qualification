<?php

namespace App\Http\Requests\Operations;

use App\Support\Fleet\PlateStates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add and edit a shipper (req 4.4). The address comes from the picker or is typed in; a pin
 * without a geocoder result is "manual", no pin clears the place reference (08 §9).
 */
class ShipperRequest extends FormRequest
{
    public const PLACE_SOURCES = ['photon', 'nominatim', 'manual'];

    private const ZIP_FORMATS = [
        'US' => '/^\d{5}(-\d{4})?$/',
        'CA' => '/^[A-Z]\d[A-Z] \d[A-Z]\d$/',
    ];

    protected function prepareForValidation(): void
    {
        $upper = fn (string $field) => is_string($this->input($field)) ? strtoupper(trim($this->input($field))) : $this->input($field);
        $zip = $upper('zip');

        // Canadian postal codes are stored as "A1A 1A1"
        if (is_string($zip) && preg_match('/^([A-Z]\d[A-Z])\s*(\d[A-Z]\d)$/', $zip, $parts)) {
            $zip = $parts[1].' '.$parts[2];
        }

        $hasPin = filled($this->input('latitude')) || filled($this->input('longitude'));
        $source = $hasPin ? (blank($this->input('place_source')) ? 'manual' : $this->input('place_source')) : null;

        $this->merge([
            'state_code' => $upper('state_code'),
            'country_code' => $upper('country_code'),
            'zip' => $zip,
            'place_source' => $source,
            // Only a geocoder result has a reference
            'place_ref' => $source !== null && $source !== 'manual' && filled($this->input('place_ref')) ? $this->input('place_ref') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $country = $this->input('country_code');
        $country = is_string($country) && isset(PlateStates::COUNTRIES[$country]) ? $country : null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'country_code' => ['required', Rule::in(array_keys(PlateStates::COUNTRIES))],
            'state_code' => ['required', 'string', 'size:2', PlateStates::rule($country)],
            'zip' => ['required', 'string', 'max:10', 'regex:'.(self::ZIP_FORMATS[$country ?? 'US'])],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'place_source' => ['nullable', Rule::in(self::PLACE_SOURCES)],
            'place_ref' => ['nullable', 'string', 'regex:/^[NWR]\d{1,19}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'state_code.exists' => 'Select a state or province of the chosen country.',
            'zip.regex' => $this->input('country_code') === 'CA' ? 'Enter a postal code like A1A 1A1.' : 'Enter a ZIP code like 12345 or 12345-6789.',
            'latitude.required_with' => 'Enter both latitude and longitude, or neither.',
            'longitude.required_with' => 'Enter both latitude and longitude, or neither.',
        ];
    }
}

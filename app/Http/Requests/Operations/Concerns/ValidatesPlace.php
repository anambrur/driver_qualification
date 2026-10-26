<?php

namespace App\Http\Requests\Operations\Concerns;

use App\Support\Fleet\PlateStates;
use Illuminate\Validation\Rule;

/**
 * An address from the map picker or typed in (req 4.4, 6.1): US or Canada, the state checked
 * against the country and the ZIP format by country. A pin without a geocoder result is
 * "manual", no pin clears the place reference (08 §9).
 */
trait ValidatesPlace
{
    /** @var list<string> */
    private static array $placeSources = ['photon', 'nominatim', 'manual'];

    /** @var array<string, string> */
    private static array $zipFormats = [
        'US' => '/^\d{5}(-\d{4})?$/',
        'CA' => '/^[A-Z]\d[A-Z] \d[A-Z]\d$/',
    ];

    /**
     * @param  array<string, mixed>  $place
     * @return array<string, mixed>
     */
    protected function normalisePlace(array $place): array
    {
        $upper = fn (mixed $value) => is_string($value) ? strtoupper(trim($value)) : $value;
        $zip = $upper($place['zip'] ?? null);

        // Canadian postal codes are stored as "A1A 1A1"
        if (is_string($zip) && preg_match('/^([A-Z]\d[A-Z])\s*(\d[A-Z]\d)$/', $zip, $parts)) {
            $zip = $parts[1].' '.$parts[2];
        }

        $hasPin = filled($place['latitude'] ?? null) || filled($place['longitude'] ?? null);
        $source = $hasPin ? (blank($place['place_source'] ?? null) ? 'manual' : $place['place_source']) : null;

        return array_merge($place, [
            'state_code' => $upper($place['state_code'] ?? null),
            'country_code' => $upper($place['country_code'] ?? null),
            'zip' => $zip,
            'place_source' => $source,
            // Only a geocoder result has a reference
            'place_ref' => $source !== null && $source !== 'manual' && filled($place['place_ref'] ?? null) ? $place['place_ref'] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function placeRules(string $prefix, mixed $country, string $addressField): array
    {
        $country = is_string($country) && isset(PlateStates::COUNTRIES[$country]) ? $country : null;

        return [
            $prefix.$addressField => ['required', 'string', 'max:255'],
            $prefix.'city' => ['required', 'string', 'max:100'],
            $prefix.'country_code' => ['required', Rule::in(array_keys(PlateStates::COUNTRIES))],
            $prefix.'state_code' => ['required', 'string', 'size:2', PlateStates::rule($country)],
            $prefix.'zip' => ['required', 'string', 'max:10', 'regex:'.self::$zipFormats[$country ?? 'US']],
            $prefix.'latitude' => ['nullable', "required_with:{$prefix}longitude", 'numeric', 'between:-90,90'],
            $prefix.'longitude' => ['nullable', "required_with:{$prefix}latitude", 'numeric', 'between:-180,180'],
            $prefix.'place_source' => ['nullable', Rule::in(self::$placeSources)],
            $prefix.'place_ref' => ['nullable', 'string', 'regex:/^[NWR]\d{1,19}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function placeMessages(string $prefix, mixed $country): array
    {
        return [
            $prefix.'state_code.exists' => 'Select a state or province of the chosen country.',
            $prefix.'zip.regex' => $country === 'CA' ? 'Enter a postal code like A1A 1A1.' : 'Enter a ZIP code like 12345 or 12345-6789.',
            $prefix.'latitude.required_with' => 'Enter both latitude and longitude, or neither.',
            $prefix.'longitude.required_with' => 'Enter both latitude and longitude, or neither.',
        ];
    }
}

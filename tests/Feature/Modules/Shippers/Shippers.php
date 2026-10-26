<?php

namespace Tests\Feature\Modules\Shippers;

use App\Models\Company;
use App\Models\Country;
use App\Models\Shipper;
use App\Models\State;

/**
 * Shipper fixtures (req 4.4).
 */
final class Shippers
{
    /**
     * Texas and Ontario, plus a state outside the US and Canada.
     */
    public static function seedStates(): void
    {
        $us = Country::forceCreate(['name' => 'United States', 'iso_code' => 'US']);
        $ca = Country::forceCreate(['name' => 'Canada', 'iso_code' => 'CA']);
        $in = Country::forceCreate(['name' => 'India', 'iso_code' => 'IN']);
        State::forceCreate(['country_id' => $us->id, 'name' => 'Texas', 'code' => 'TX']);
        State::forceCreate(['country_id' => $ca->id, 'name' => 'Ontario', 'code' => 'ON']);
        State::forceCreate(['country_id' => $in->id, 'name' => 'Maharashtra', 'code' => 'MH']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Acme Foods DC',
            'contact_name' => 'Dana Ruiz',
            'phone' => '214-555-0100',
            'email' => 'dock@acme.test',
            'address_line1' => '1200 Commerce St',
            'address_line2' => 'Dock 4',
            'city' => 'Dallas',
            'state_code' => 'TX',
            'zip' => '75202',
            'country_code' => 'US',
            'latitude' => '32.7767000',
            'longitude' => '-96.7970000',
            'place_source' => 'photon',
            'place_ref' => 'W123456',
            'notes' => 'Check in at gate 2.',
        ], $overrides);
    }

    public static function latest(Company $company): ?Shipper
    {
        return Shipper::withoutTenancy()->withTrashed()->where('company_id', $company->id)->latest('id')->first();
    }
}

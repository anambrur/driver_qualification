<?php

namespace App\Services\Maps;

use App\Support\Fleet\PlateStates;

/**
 * Nominatim (nominatim.openstreetmap.org by default). Searched on a button only: the public
 * policy forbids autocomplete and allows 1 request per second for the whole app (14 §4).
 */
final class NominatimGeocoder extends HttpGeocoder
{
    private const OSM_TYPES = ['node' => 'N', 'way' => 'W', 'relation' => 'R'];

    protected function source(): string
    {
        return 'nominatim';
    }

    protected function fetchSearch(string $query): array
    {
        $json = $this->getJson('/search', [
            'q' => $query,
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'limit' => (int) config('maps.geocoder.limit'),
            'countrycodes' => strtolower(implode(',', array_keys(PlateStates::COUNTRIES))),
            'accept-language' => 'en',
        ]);

        return array_values(array_filter(array_map(fn ($result) => is_array($result) ? $this->place($result) : null, $json)));
    }

    protected function fetchReverse(float $lat, float $lng): ?Place
    {
        $json = $this->getJson('/reverse', ['lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2', 'addressdetails' => 1, 'accept-language' => 'en']);

        return isset($json['error']) ? null : $this->place($json);
    }

    /**
     * @param  array<mixed>  $result
     */
    private function place(array $result): ?Place
    {
        $address = (array) ($result['address'] ?? []);
        $country = strtoupper((string) ($address['country_code'] ?? ''));

        if (! isset(PlateStates::COUNTRIES[$country]) || ! isset($result['lat'], $result['lon'])) {
            return null;
        }

        $street = trim(($address['house_number'] ?? '').' '.($address['road'] ?? '')) ?: null;
        $city = $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['hamlet'] ?? null;
        $iso = (string) ($address['ISO3166-2-lvl4'] ?? '');
        $state = str_starts_with($iso, $country.'-') && strlen($iso) === 5
            ? substr($iso, 3)
            : $this->stateCode($country, $address['state'] ?? null);
        $osmType = self::OSM_TYPES[$result['osm_type'] ?? ''] ?? null;

        return new Place(
            label: (string) ($result['display_name'] ?? self::label([$street, $city, $state, $country])),
            addressLine1: $street,
            city: $city,
            stateCode: $state,
            zip: isset($address['postcode']) ? (string) $address['postcode'] : null,
            countryCode: $country,
            lat: (float) $result['lat'],
            lng: (float) $result['lon'],
            source: 'nominatim',
            ref: $osmType !== null && isset($result['osm_id']) ? $osmType.$result['osm_id'] : null,
        );
    }
}

<?php

namespace App\Services\Maps;

use App\Support\Fleet\PlateStates;

/**
 * Photon (photon.komoot.io by default): GeoJSON features with OpenStreetMap address parts. The
 * state comes as a name and is mapped to its code.
 */
final class PhotonGeocoder extends HttpGeocoder
{
    protected function source(): string
    {
        return 'photon';
    }

    protected function fetchSearch(string $query): array
    {
        $json = $this->getJson('/api', ['q' => $query, 'limit' => (int) config('maps.geocoder.limit') * 2, 'lang' => 'en'], self::countryFilter());

        return $this->places($json);
    }

    protected function fetchReverse(float $lat, float $lng): ?Place
    {
        return $this->places($this->getJson('/reverse', ['lat' => $lat, 'lon' => $lng, 'limit' => 1, 'lang' => 'en']))[0] ?? null;
    }

    /** countrycode repeats, which http_build_query can't write */
    private static function countryFilter(): string
    {
        return implode('', array_map(fn (string $iso) => '&countrycode='.$iso, array_keys(PlateStates::COUNTRIES)));
    }

    /**
     * @param  array<mixed>  $json
     * @return list<Place>
     */
    private function places(array $json): array
    {
        $places = [];

        foreach ((array) ($json['features'] ?? []) as $feature) {
            $props = (array) ($feature['properties'] ?? []);
            $coordinates = (array) ($feature['geometry']['coordinates'] ?? []);
            $country = strtoupper((string) ($props['countrycode'] ?? ''));

            if (! isset(PlateStates::COUNTRIES[$country]) || count($coordinates) < 2) {
                continue;
            }

            $type = (string) ($props['type'] ?? '');
            $name = isset($props['name']) ? (string) $props['name'] : null;
            $street = trim(($props['housenumber'] ?? '').' '.($props['street'] ?? '')) ?: ($type === 'house' ? $name : null);
            $city = $props['city'] ?? ($type === 'city' ? $name : null);
            $osmType = (string) ($props['osm_type'] ?? '');

            $places[] = new Place(
                label: self::label([$name, $street, $city, $props['state'] ?? null, $props['postcode'] ?? null, $country]),
                addressLine1: $street,
                city: $city,
                stateCode: $this->stateCode($country, $props['state'] ?? null),
                zip: isset($props['postcode']) ? (string) $props['postcode'] : null,
                countryCode: $country,
                lat: (float) $coordinates[1],
                lng: (float) $coordinates[0],
                source: 'photon',
                ref: in_array($osmType, ['N', 'W', 'R'], true) && isset($props['osm_id']) ? $osmType.$props['osm_id'] : null,
            );
        }

        return $places;
    }
}

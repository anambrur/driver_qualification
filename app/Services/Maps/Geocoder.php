<?php

namespace App\Services\Maps;

/**
 * Address search behind the map picker (req 6.1). Drivers: photon, nominatim, none
 * (config/maps.php). Results are US and Canada only.
 */
interface Geocoder
{
    public function enabled(): bool;

    /**
     * @return list<Place>
     *
     * @throws GeocoderUnavailable
     */
    public function search(string $query): array;

    /**
     * @throws GeocoderUnavailable
     */
    public function reverse(float $lat, float $lng): ?Place;
}

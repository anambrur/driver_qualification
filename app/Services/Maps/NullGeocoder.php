<?php

namespace App\Services\Maps;

/**
 * MAPS_GEOCODER=none: search is off and addresses are typed in.
 */
final class NullGeocoder implements Geocoder
{
    public function enabled(): bool
    {
        return false;
    }

    public function search(string $query): array
    {
        return [];
    }

    public function reverse(float $lat, float $lng): ?Place
    {
        return null;
    }
}

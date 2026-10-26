<?php

return [
    // Address search (req 6.1, 14 §2 "Maps"): photon, nominatim, or none (search off, manual entry).
    // Switching off is an env change, as the Nominatim policy requires (14 §4).
    'geocoder' => [
        'driver' => env('MAPS_GEOCODER', 'photon'),
        'url' => env('MAPS_GEOCODER_URL'),
        'urls' => [
            'photon' => 'https://photon.komoot.io',
            'nominatim' => 'https://nominatim.openstreetmap.org',
        ],
        // Sent in the User-Agent so the service can reach us
        'contact' => env('MAPS_GEOCODER_CONTACT', env('MAIL_FROM_ADDRESS')),
        'timeout' => (int) env('MAPS_GEOCODER_TIMEOUT', 4),
        // App-wide, every user together: Nominatim's absolute maximum is 1 request per second
        'per_second' => 1,
        'cache_days' => (int) env('MAPS_GEOCODER_CACHE_DAYS', 30),
        'limit' => 5,
    ],

    // Leaflet tiles, loaded by the browser. The attribution is always shown on the map.
    'tiles' => [
        'url' => env('MAPS_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => env('MAPS_TILE_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'),
    ],
];

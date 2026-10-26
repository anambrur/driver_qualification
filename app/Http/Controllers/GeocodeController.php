<?php

namespace App\Http\Controllers;

use App\Services\Maps\Geocoder;
use App\Services\Maps\GeocoderUnavailable;
use App\Services\Maps\HttpGeocoder;
use App\Services\Maps\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Address search for the map picker (req 6.1). A failed or switched-off search answers with no
 * results and a message, so the address can still be typed in.
 */
class GeocodeController extends Controller
{
    private const OFF = 'Address search is off. Enter the address by hand.';

    public function __construct(private Geocoder $geocoder) {}

    public function search(Request $request): JsonResponse
    {
        $query = $request->validate(['q' => ['required', 'string', 'min:'.HttpGeocoder::MIN_QUERY, 'max:200']])['q'];

        return $this->answer(fn () => $this->geocoder->search($query));
    }

    public function reverse(Request $request): JsonResponse
    {
        $point = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return $this->answer(fn () => array_filter([$this->geocoder->reverse((float) $point['lat'], (float) $point['lng'])]));
    }

    /**
     * @param  callable(): array<Place>  $find
     */
    private function answer(callable $find): JsonResponse
    {
        if (! $this->geocoder->enabled()) {
            return response()->json(['results' => [], 'message' => self::OFF]);
        }

        try {
            $places = array_values($find());
        } catch (GeocoderUnavailable $e) {
            return response()->json(['results' => [], 'message' => $e->getMessage()]);
        }

        return response()->json([
            'results' => array_map(fn (Place $place) => $place->toArray(), $places),
            'message' => $places === [] ? 'No matching address in the US or Canada.' : null,
        ]);
    }
}

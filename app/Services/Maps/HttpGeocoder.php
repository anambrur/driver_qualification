<?php

namespace App\Services\Maps;

use App\Support\Fleet\PlateStates;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * The public OpenStreetMap geocoders (14 §4): an identifying User-Agent, a short timeout, results
 * cached by normalised query, and one app-wide request rate for every user together. A failed
 * call is never cached.
 */
abstract class HttpGeocoder implements Geocoder
{
    public const MIN_QUERY = 3;

    /** @var array<string, array<string, string>>|null */
    private ?array $stateCodes = null;

    abstract protected function source(): string;

    /**
     * @return list<Place>
     */
    abstract protected function fetchSearch(string $query): array;

    abstract protected function fetchReverse(float $lat, float $lng): ?Place;

    public function enabled(): bool
    {
        return true;
    }

    public function search(string $query): array
    {
        $query = self::normalise($query);

        if (mb_strlen($query) < self::MIN_QUERY) {
            return [];
        }

        $places = $this->cached('search:'.sha1($query), fn () => array_map(
            fn (Place $place) => $place->toArray(),
            array_slice($this->fetchSearch($query), 0, (int) config('maps.geocoder.limit')),
        ));

        return array_map(fn (array $place) => Place::fromArray($place), $places);
    }

    public function reverse(float $lat, float $lng): ?Place
    {
        $place = $this->cached(sprintf('reverse:%.5f,%.5f', $lat, $lng), fn () => $this->fetchReverse($lat, $lng)?->toArray());

        return $place === null ? null : Place::fromArray($place);
    }

    public static function normalise(string $query): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($query)));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fetch
     * @return T
     */
    private function cached(string $key, callable $fetch): mixed
    {
        $key = 'maps:'.$this->source().':'.$key;

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $limiter = 'maps:geocoder:'.$this->source();

        if (RateLimiter::tooManyAttempts($limiter, (int) config('maps.geocoder.per_second'))) {
            throw GeocoderUnavailable::busy();
        }

        RateLimiter::hit($limiter, 1);
        $result = $fetch();

        // "Nothing found" may change as the map is edited, so it is kept for a day only
        Cache::put($key, $result, empty($result) ? now()->addDay() : now()->addDays((int) config('maps.geocoder.cache_days')));

        return $result;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    protected function getJson(string $path, array $query, string $extra = ''): array
    {
        $url = rtrim((string) (config('maps.geocoder.url') ?: config('maps.geocoder.urls.'.$this->source())), '/').$path
            .'?'.http_build_query($query).$extra;

        try {
            $response = $this->client()->get($url);
        } catch (Throwable $e) {
            Log::warning('Geocoder request failed', ['driver' => $this->source(), 'error' => $e::class]);

            throw GeocoderUnavailable::failed();
        }

        if (! $response->successful() || ! is_array($response->json())) {
            $this->logFailure($response);

            throw GeocoderUnavailable::failed();
        }

        return $response->json();
    }

    protected function stateCode(string $country, ?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }

        $this->stateCodes ??= PlateStates::codesByName();

        return $this->stateCodes[$country][mb_strtolower($name)] ?? null;
    }

    /**
     * @param  list<string|null>  $parts
     */
    protected static function label(array $parts): string
    {
        return implode(', ', array_values(array_unique(array_filter($parts, fn ($part) => $part !== null && $part !== ''))));
    }

    private function client(): PendingRequest
    {
        $timeout = (int) config('maps.geocoder.timeout');
        $contact = config('maps.geocoder.contact');

        return Http::withUserAgent(trim(config('app.name').'/1.0 (+'.config('app.url').($contact ? '; '.$contact : '').')'))
            ->acceptJson()
            ->connectTimeout($timeout)
            ->timeout($timeout);
    }

    private function logFailure(Response $response): void
    {
        Log::warning('Geocoder request failed', ['driver' => $this->source(), 'status' => $response->status()]);
    }
}

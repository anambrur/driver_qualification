<?php

namespace Tests\Feature\Modules\Trips;

use App\Models\Company;
use App\Models\Trip;
use App\Models\User;
use App\Services\Operations\TripService;
use App\Tenancy\TenantContext;
use Closure;

/**
 * Trip fixtures (req 4.1–4.3, 6.1, 6.3). Shippers::seedStates() provides TX and ON.
 */
final class Trips
{
    /**
     * Runs $callback with TripService inside the company's tenant context.
     *
     * @template T
     *
     * @param  Closure(TripService): T  $callback
     * @return T
     */
    public static function service(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->run($company->id, fn () => $callback(app(TripService::class)));
    }

    /**
     * A draft created through TripService, as the create page does.
     *
     * @param  array<string, mixed>  $data
     */
    public static function create(Company $company, array $data = [], ?User $actor = null): Trip
    {
        return self::service($company, fn (TripService $trips) => $trips->create($data + ['weight_unit' => 'lb'], $actor ?? $company->owner));
    }

    /**
     * One stop's validated fields (Dallas unless overridden).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function stop(array $overrides = []): array
    {
        return array_merge([
            'address_line' => '1200 Commerce St',
            'city' => 'Dallas',
            'state_code' => 'TX',
            'zip' => '75202',
            'country_code' => 'US',
            'latitude' => '32.7767000',
            'longitude' => '-96.7970000',
            'place_source' => 'photon',
            'place_ref' => 'W123456',
            'shipper_id' => null,
        ], $overrides);
    }

    /**
     * Start in Dallas, drop-off in Houston, optionally a pickup in Fort Worth.
     *
     * @return array{start: array<string, mixed>, pickup: array<string, mixed>|null, drop_off: array<string, mixed>}
     */
    public static function route(bool $pickup = false, bool $alsoPickup = false): array
    {
        return [
            'start' => self::stop(['also_pickup' => $alsoPickup]),
            'pickup' => $pickup ? self::stop(['address_line' => '500 Main St', 'city' => 'Fort Worth', 'zip' => '76102', 'latitude' => '32.7555', 'longitude' => '-97.3308']) : null,
            'drop_off' => self::stop(['address_line' => '901 Bagby St', 'city' => 'Houston', 'zip' => '77002', 'latitude' => '29.7604', 'longitude' => '-95.3698']),
        ];
    }

    /**
     * The stops form as posted (the route above).
     *
     * @return array<string, mixed>
     */
    public static function stopsPayload(bool $pickup = false, bool $alsoPickup = false): array
    {
        $route = self::route($pickup, $alsoPickup);

        return [
            'has_pickup' => $pickup ? '1' : null,
            'stops' => array_filter([
                'start' => array_filter($route['start'], fn ($value) => $value !== false),
                'pickup' => $route['pickup'],
                'drop_off' => $route['drop_off'],
            ]),
        ];
    }

    /**
     * The details form as posted.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function details(array $overrides = []): array
    {
        return array_merge([
            'product' => 'Frozen food',
            'weight' => '42000',
            'weight_unit' => 'lb',
            'planned_distance_miles' => '239.5',
            'notes' => 'Dock 4.',
        ], $overrides);
    }

    public static function confirmAndRoute(Trip $trip, Company $company): Trip
    {
        return self::service($company, function (TripService $trips) use ($trip, $company) {
            $trips->confirmNumber($trip, $company->owner);

            return $trips->saveStops($trip, self::route(), $company->owner);
        });
    }
}

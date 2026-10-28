<?php

namespace Tests\Feature\Modules\Trips;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Trailer;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Operations\TripAssignmentService;
use App\Services\Operations\TripLifecycleService;
use App\Services\Operations\TripService;
use App\Tenancy\TenantContext;
use Closure;
use Database\Factories\DriverFactory;
use Database\Factories\InspectionFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;

/**
 * Trip fixtures (req 4.1–4.6, 6.1, 6.3). Shippers::seedStates() provides TX and ON.
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

    public static function confirmAndRoute(Trip $trip, Company $company, bool $pickup = false, bool $alsoPickup = false): Trip
    {
        return self::service($company, function (TripService $trips) use ($trip, $company, $pickup, $alsoPickup) {
            $trips->confirmNumber($trip, $company->owner);

            return $trips->saveStops($trip, self::route($pickup, $alsoPickup), $company->owner);
        });
    }

    /**
     * An active driver, a vehicle and a trailer of the company.
     *
     * @return array{driver: Driver, vehicle: Vehicle, trailer: Trailer}
     */
    public static function crew(Company $company): array
    {
        return [
            'driver' => DriverFactory::new()->forCompany($company)->withCurrentLicense()->create(),
            'vehicle' => VehicleFactory::new()->forCompany($company)->create(),
            'trailer' => TrailerFactory::new()->forCompany($company)->create(),
        ];
    }

    /**
     * Runs $callback with TripAssignmentService and TripLifecycleService in the company's context.
     *
     * @template T
     *
     * @param  Closure(TripAssignmentService, TripLifecycleService): T  $callback
     * @return T
     */
    public static function operate(Company $company, Closure $callback): mixed
    {
        return app(TenantContext::class)->run($company->id, fn () => $callback(app(TripAssignmentService::class), app(TripLifecycleService::class)));
    }

    /**
     * A planned trip (confirmed, routed), optionally with a pickup or with the start as the pickup.
     */
    public static function planned(Company $company, bool $pickup = false, bool $alsoPickup = false): Trip
    {
        return self::confirmAndRoute(self::create($company), $company, $pickup, $alsoPickup);
    }

    /**
     * A planned trip assigned through the service, with the equipment confirmed unless asked not to.
     * Returned without relations, so refresh() works outside a tenant context.
     *
     * @param  array{driver: Driver, vehicle: Vehicle, trailer: Trailer}|null  $crew
     */
    public static function assigned(Company $company, ?array $crew = null, bool $confirmed = true, bool $pickup = false, bool $alsoPickup = false): Trip
    {
        $crew ??= self::crew($company);
        $trip = self::planned($company, $pickup, $alsoPickup);

        return self::operate($company, function (TripAssignmentService $assignment) use ($trip, $crew, $company, $confirmed) {
            $trip = $assignment->assign($trip, ['driver_id' => $crew['driver']->id, 'vehicle_id' => $crew['vehicle']->id, 'trailer_id' => $crew['trailer']->id], $company->owner);

            return ($confirmed ? $assignment->confirmEquipment($trip, $company->owner) : $trip)->withoutRelations();
        });
    }

    /**
     * An assigned trip started through the service.
     *
     * @param  array{driver: Driver, vehicle: Vehicle, trailer: Trailer}|null  $crew
     */
    public static function inProgress(Company $company, ?array $crew = null, bool $pickup = false, bool $alsoPickup = false): Trip
    {
        $trip = self::inspected(self::assigned($company, $crew, true, $pickup, $alsoPickup));

        return self::operate($company, fn ($assignment, TripLifecycleService $lifecycle) => $lifecycle->start($trip, $company->owner)->withoutRelations());
    }

    /**
     * Records a completed pre-trip inspection of the trip's current crew (P2-4), so the pre-trip
     * gate lets it start.
     */
    public static function inspected(Trip $trip, string $result = 'no_defects'): Trip
    {
        InspectionFactory::new()->forTrip($trip)->completed($result)->create();

        return $trip;
    }
}

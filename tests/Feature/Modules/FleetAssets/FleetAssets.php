<?php

namespace Tests\Feature\Modules\FleetAssets;

use App\Models\EquipmentType;
use App\Models\Trailer;
use App\Models\User;
use App\Models\Vehicle;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Tests\Support\Actors;

/**
 * Fixtures for the vehicle (admin.vehicle.*) and trailer (admin.trailer.*) pages.
 * Most helpers take an asset type ('vehicle' or 'trailer').
 */
final class FleetAssets
{
    public const AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

    public const TYPES = ['vehicle', 'trailer'];

    /**
     * A subscribed tenant and one asset of the given type in their company.
     *
     * @return array{0: User, 1: Vehicle|Trailer}
     */
    public static function tenantWithAsset(string $assetType = 'vehicle', array $attributes = []): array
    {
        $owner = Actors::companyOwner();

        return [$owner, self::assetOf($owner, $assetType, $attributes)];
    }

    public static function assetOf(User $owner, string $assetType = 'vehicle', array $attributes = []): Vehicle|Trailer
    {
        $factory = $assetType === 'vehicle' ? VehicleFactory::new() : TrailerFactory::new();

        return $factory->forCompany(Actors::companyOf($owner))->create($attributes);
    }

    /**
     * @return class-string<Vehicle|Trailer>
     */
    public static function model(string $assetType): string
    {
        return $assetType === 'vehicle' ? Vehicle::class : Trailer::class;
    }

    public static function route(string $assetType, string $action, mixed $parameters = []): string
    {
        return route("admin.{$assetType}.{$action}", $parameters);
    }

    /**
     * A valid create/update payload for the asset type.
     *
     * @return array<string, mixed>
     */
    public static function payload(string $assetType, array $overrides = []): array
    {
        $base = [
            'unit_no' => 'U-'.fake()->unique()->numerify('#####'),
            'vin' => strtoupper(fake()->unique()->bothify('1FU??##########??')),
            'year' => 2020,
            'make' => 'Freightliner',
            'model' => 'Cascadia',
        ];

        $base += $assetType === 'vehicle'
            ? ['odometer' => 1000]
            : ['equipment_types_id' => EquipmentType::query()->firstOrCreate(['name' => 'Dry Van'])->id];

        return array_merge($base, $overrides);
    }
}

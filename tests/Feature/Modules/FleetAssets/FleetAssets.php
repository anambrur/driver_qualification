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
 * Fixtures for vehicles (admin.vehicle.*) and trailers (admin.trailer.*).
 */
final class FleetAssets
{
    public static function vehicleOf(User $owner, array $attributes = []): Vehicle
    {
        return VehicleFactory::new()->forCompany(Actors::companyOf($owner))->create($attributes);
    }

    public static function trailerOf(User $owner, array $attributes = []): Trailer
    {
        return TrailerFactory::new()->forCompany(Actors::companyOf($owner))->create($attributes);
    }

    /**
     * Headers DataTables sends ($request->ajax() checks X-Requested-With).
     *
     * @return array<string, string>
     */
    public static function ajax(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
    }

    public static function vin(): string
    {
        // VINs never contain I, O or Q
        return fake()->unique()->regexify('1FA[A-HJ-NPR-Z]{2}[0-9]{10}[A-HJ-NPR-Z]{2}');
    }

    /**
     * @return array<string, mixed>
     */
    public static function vehiclePayload(array $overrides = []): array
    {
        return array_merge([
            'unit_no' => 'TRK-'.fake()->unique()->numerify('#####'),
            'vin' => self::vin(),
            'year' => 2020,
            'make' => 'Peterbilt',
            'model' => '389',
            'odometer' => 1000,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function trailerPayload(array $overrides = []): array
    {
        return array_merge([
            'unit_no' => 'TRL-'.fake()->unique()->numerify('#####'),
            'vin' => self::vin(),
            'year' => 2020,
            'make' => 'Wabash',
            'model' => 'Van-53',
            'equipment_types_id' => EquipmentType::query()->firstOrCreate(['name' => 'Dry Van'])->id,
        ], $overrides);
    }

    /**
     * The edit form posts every field back; this is the vehicle's current data as the form sends it.
     *
     * @return array<string, mixed>
     */
    public static function vehicleFormData(Vehicle $vehicle, array $overrides = []): array
    {
        return array_merge($vehicle->only([
            'company_id', 'unit_no', 'vin', 'year', 'make', 'model', 'odometer',
        ]), $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function trailerFormData(Trailer $trailer, array $overrides = []): array
    {
        return array_merge($trailer->only([
            'company_id', 'unit_no', 'vin', 'year', 'make', 'model', 'equipment_types_id',
        ]), $overrides);
    }

    /**
     * $kind is 'vehicle' or 'trailer', the same word the route names use.
     */
    public static function assetOf(User $owner, string $kind, array $attributes = []): Vehicle|Trailer
    {
        return $kind === 'vehicle' ? self::vehicleOf($owner, $attributes) : self::trailerOf($owner, $attributes);
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(string $kind, array $overrides = []): array
    {
        return $kind === 'vehicle' ? self::vehiclePayload($overrides) : self::trailerPayload($overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function formData(Vehicle|Trailer $asset, array $overrides = []): array
    {
        return $asset instanceof Vehicle ? self::vehicleFormData($asset, $overrides) : self::trailerFormData($asset, $overrides);
    }

    /**
     * @return class-string<Vehicle|Trailer>
     */
    public static function model(string $kind): string
    {
        return $kind === 'vehicle' ? Vehicle::class : Trailer::class;
    }
}

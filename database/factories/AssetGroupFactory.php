<?php

namespace Database\Factories;

use App\Models\AssetGroup;
use App\Models\Company;
use App\Models\Vehicle;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * company_id defaults to the vehicle's company; the driver (and trailer, if any) are created in
 * that same company (the composite FKs reject anything else).
 *
 * @extends Factory<AssetGroup>
 */
class AssetGroupFactory extends Factory
{
    use CreatesInTenant;

    protected $model = AssetGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_name' => 'GR#'.fake()->unique()->numerify('#####'),
            'vehicle_id' => VehicleFactory::new(),
            'company_id' => fn (array $attributes) => self::companyOfVehicle($attributes['vehicle_id'])->id,
            'driver_id' => fn (array $attributes) => DriverFactory::new()->forCompany(self::companyOfVehicle($attributes['vehicle_id'])),
            'trailer_id' => null,
            'status' => 'active',
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['vehicle_id' => VehicleFactory::new()->forCompany($company)]);
    }

    public function withTrailer(): static
    {
        return $this->state([
            'trailer_id' => fn (array $attributes) => TrailerFactory::new()->forCompany(self::companyOfVehicle($attributes['vehicle_id'])),
        ]);
    }

    private static function companyOfVehicle(int $vehicleId): Company
    {
        return Company::findOrFail(Vehicle::withoutTenancy()->withTrashed()->findOrFail($vehicleId)->company_id);
    }
}

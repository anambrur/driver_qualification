<?php

namespace Database\Factories;

use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
    use CreatesInTenant;

    protected $model = MaintenanceSchedule::class;

    /**
     * A date-based schedule for one vehicle, filed under the vehicle's company.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehicle_id' => VehicleFactory::new(),
            'company_id' => fn (array $attributes) => $attributes['vehicle_id'] === null
                ? CompanyFactory::new()
                : Vehicle::withoutTenancy()->withTrashed()->whereKey($attributes['vehicle_id'])->value('company_id'),
            'maintenance_category_id' => MaintenanceCategoryFactory::new(),
            'title' => fake()->words(3, true),
            'schedule_type' => 'date',
            'interval_days' => 90,
            'interval_miles' => null,
            'interval_hours' => null,
            'status' => 'active',
        ];
    }

    public function forVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn () => ['vehicle_id' => $vehicle->id, 'company_id' => $vehicle->company_id]);
    }

    public function mileage(int $miles = 5000): static
    {
        return $this->state(fn () => ['schedule_type' => 'mileage', 'interval_days' => null, 'interval_miles' => $miles, 'interval_hours' => null]);
    }

    public function engineHours(int $hours = 250): static
    {
        return $this->state(fn () => ['schedule_type' => 'engine_hours', 'interval_days' => null, 'interval_miles' => null, 'interval_hours' => $hours]);
    }
}

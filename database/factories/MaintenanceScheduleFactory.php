<?php

namespace Database\Factories;

use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
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
                : Vehicle::withTrashed()->whereKey($attributes['vehicle_id'])->value('company_id'),
            'maintenance_category_id' => MaintenanceCategoryFactory::new(),
            'title' => fake()->words(3, true),
            'schedule_type' => 'date',
            'interval_days' => 90,
            'interval_miles' => null,
            'interval_hours' => null,
            'status' => 'active',
            'is_active' => true,
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

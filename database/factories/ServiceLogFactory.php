<?php

namespace Database\Factories;

use App\Models\ServiceLog;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceLog>
 */
class ServiceLogFactory extends Factory
{
    protected $model = ServiceLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $odometer = fake()->numberBetween(0, 500000);

        return [
            'vehicle_id' => VehicleFactory::new(),
            'company_id' => fn (array $attributes) => Vehicle::withTrashed()->whereKey($attributes['vehicle_id'])->value('company_id'),
            'service_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'maintenance_notes' => fake()->sentence(),
            'odometer_at_service' => $odometer,
            'current_odometer' => $odometer,
            'engine_hours_at_service' => null,
            'current_engine_hours' => null,
            'total_cost' => fake()->randomFloat(2, 0, 5000),
            'status' => 'completed',
        ];
    }

    /**
     * A log for the given vehicle, filed under the vehicle's company.
     */
    public function forVehicle(Vehicle $vehicle): static
    {
        return $this->state(fn () => ['vehicle_id' => $vehicle->id, 'company_id' => $vehicle->company_id]);
    }
}

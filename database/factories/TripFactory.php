<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Trip;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Trips straight into a status, without TripService (no sequence row, no folder). Assigned and
 * later states get a driver and a vehicle of the trip's company (the status CHECK).
 *
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    use CreatesInTenant;

    protected $model = Trip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'trip_number' => fake()->unique()->numberBetween(100_000, 9_999_999),
            'trip_date' => now()->toDateString(),
            'status' => 'draft',
            'product' => fake()->randomElement(['Frozen food', 'Steel coils', 'Lumber', 'Produce']),
            'weight' => fake()->numberBetween(5_000, 44_000),
            'weight_unit' => 'lb',
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['number_confirmed_at' => now()]);
    }

    public function planned(): static
    {
        return $this->confirmed()->state(fn () => ['status' => 'planned']);
    }

    public function assigned(): static
    {
        return $this->confirmed()->withCrew()->state(fn () => ['status' => 'assigned']);
    }

    public function inProgress(): static
    {
        return $this->confirmed()->withCrew()->state(fn () => ['status' => 'in_progress', 'started_at' => now()]);
    }

    public function completed(): static
    {
        return $this->confirmed()->withCrew()->state(fn () => ['status' => 'completed', 'started_at' => now()->subHours(6), 'completed_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => 'Load fell through']);
    }

    /**
     * A driver and a vehicle of the trip's company.
     */
    public function withCrew(): static
    {
        // Attribute closures see company_id already resolved
        return $this->state(fn () => [
            'driver_id' => fn (array $attributes) => DriverFactory::new()->state(['company_id' => $attributes['company_id']]),
            'vehicle_id' => fn (array $attributes) => VehicleFactory::new()->state(['company_id' => $attributes['company_id']]),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\FuelPurchase;
use App\Models\TripEvent;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fuel purchases straight into the table. Use forEvent() with a fuel trip event.
 *
 * @extends Factory<FuelPurchase>
 */
class FuelPurchaseFactory extends Factory
{
    use CreatesInTenant;

    protected $model = FuelPurchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchased_at' => now(),
            'state_code' => 'TX',
            'gallons' => '100.000',
            'price_per_gallon' => '3.899',
            'total_amount' => '389.90',
            'currency' => FuelPurchase::CURRENCY,
        ];
    }

    public function forEvent(TripEvent $event): static
    {
        return $this->state(fn () => [
            'company_id' => $event->company_id,
            'trip_id' => $event->trip_id,
            'trip_event_id' => $event->id,
            'driver_id' => $event->driver_id,
            'vehicle_id' => $event->vehicle_id,
            'purchased_at' => $event->occurred_at,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn () => ['confirmed_at' => now()]);
    }
}

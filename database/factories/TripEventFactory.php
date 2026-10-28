<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripEvent;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Trip events straight into the table, without TripEventService. Use forTrip() so the driver
 * and vehicle match the trip; the default is a stop with a reason (the stop CHECK).
 *
 * @extends Factory<TripEvent>
 */
class TripEventFactory extends Factory
{
    use CreatesInTenant;

    protected $model = TripEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'stop',
            'occurred_at' => now(),
            'reason' => 'Rest break',
        ];
    }

    public function forTrip(Trip $trip): static
    {
        return $this->state(fn () => [
            'company_id' => $trip->company_id,
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'vehicle_id' => $trip->vehicle_id,
        ]);
    }

    public function ofType(string $type): static
    {
        return $this->state(fn () => match ($type) {
            'police_stop' => ['type' => $type, 'ticket_issued' => false, 'reason' => 'Warning only'],
            'weigh_station' => ['type' => $type, 'weight_ok' => true, 'reason' => null],
            'accident' => ['type' => $type, 'injuries_count' => 0, 'fatalities_count' => 0, 'hazmat_released' => false, 'reason' => null],
            default => ['type' => $type, 'reason' => $type === 'stop' ? 'Rest break' : null],
        });
    }

    public function voided(string $reason = 'Recorded twice'): static
    {
        return $this->state(fn () => ['voided_at' => now(), 'void_reason' => $reason]);
    }
}

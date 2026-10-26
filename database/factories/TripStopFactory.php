<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripStop;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<TripStop>
 */
class TripStopFactory extends Factory
{
    use CreatesInTenant;

    protected $model = TripStop::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => TripFactory::new(),
            'company_id' => fn (array $attributes) => DB::table('trips')->where('id', $attributes['trip_id'])->value('company_id'),
            'sequence' => 1,
            'stop_type' => 'start',
            'address_line' => fake()->streetAddress(),
            'city' => 'Dallas',
            'state_code' => 'TX',
            'zip' => fake()->numerify('75###'),
            'country_code' => 'US',
            'latitude' => 32.7767,
            'longitude' => -96.797,
            'place_source' => 'manual',
        ];
    }

    public function onTrip(Trip $trip, string $type, int $sequence): static
    {
        return $this->state(fn () => ['trip_id' => $trip->id, 'stop_type' => $type, 'sequence' => $sequence]);
    }
}

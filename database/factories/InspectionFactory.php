<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionTemplate;
use App\Models\Trip;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Inspections straight into a status, without InspectionService. Use forTrip() so the crew
 * matches the trip.
 *
 * @extends Factory<Inspection>
 */
class InspectionFactory extends Factory
{
    use CreatesInTenant;

    protected $model = Inspection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => fn () => InspectionTemplate::query()->activeOf('pre_trip')->value('id')
                ?? InspectionTemplateFactory::new()->active()->withItems()->create()->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ];
    }

    public function forTrip(Trip $trip): static
    {
        return $this->state(fn () => [
            'company_id' => $trip->company_id,
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'vehicle_id' => $trip->vehicle_id,
            'trailer_id' => $trip->trailer_id,
        ]);
    }

    public function completed(string $result = 'no_defects'): static
    {
        return $this->state(fn () => ['status' => 'completed', 'result' => $result, 'completed_at' => now()]);
    }

    public function abandoned(): static
    {
        return $this->state(fn () => ['status' => 'abandoned']);
    }
}

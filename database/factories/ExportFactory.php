<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Export;
use App\Models\StoredFile;
use App\Models\Trip;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Export>
 */
class ExportFactory extends Factory
{
    use CreatesInTenant;

    protected $model = Export::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'type' => 'zip_company',
            'parameters' => [],
            'status' => 'queued',
        ];
    }

    public function forDriver(Driver $driver): static
    {
        return $this->state(fn () => ['company_id' => $driver->company_id, 'type' => 'zip_driver', 'parameters' => ['driver_id' => $driver->id]]);
    }

    public function forTrip(Trip $trip): static
    {
        return $this->state(fn () => ['company_id' => $trip->company_id, 'type' => 'zip_trip', 'parameters' => ['trip_id' => $trip->id]]);
    }

    /**
     * Built: $file is the ZIP, downloadable for 7 days.
     */
    public function ready(StoredFile $file): static
    {
        return $this->state(fn () => [
            'company_id' => $file->company_id,
            'status' => 'ready',
            'file_id' => $file->id,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }
}

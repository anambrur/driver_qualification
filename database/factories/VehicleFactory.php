<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Vehicle;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    use CreatesInTenant;

    protected $model = Vehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'unit_no' => 'TRK-'.fake()->unique()->numerify('#####'),
            // VINs never contain I, O or Q
            'vin' => fake()->unique()->regexify('1HG[A-HJ-NPR-Z]{2}[0-9]{10}[A-HJ-NPR-Z]{2}'),
            'year' => (int) fake()->numberBetween(2005, (int) date('Y')),
            'make' => fake()->randomElement(['Freightliner', 'Peterbilt', 'Kenworth', 'Volvo']),
            'model' => fake()->bothify('Model-##'),
            'odometer' => fake()->numberBetween(0, 900000),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'unit_no' => 'TRK-'.fake()->unique()->numerify('#####'),
            'vin' => Str::upper(fake()->unique()->bothify('1HG??#########??')),
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

<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\EquipmentType;
use App\Models\Trailer;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trailer>
 */
class TrailerFactory extends Factory
{
    use CreatesInTenant;

    protected $model = Trailer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'unit_no' => 'TRL-'.fake()->unique()->numerify('#####'),
            // VINs never contain I, O or Q
            'vin' => fake()->unique()->regexify('1JJ[A-HJ-NPR-Z]{2}[0-9]{10}[A-HJ-NPR-Z]{2}'),
            'year' => (int) fake()->numberBetween(2005, (int) date('Y')),
            'make' => fake()->randomElement(['Utility', 'Wabash', 'Great Dane']),
            'model' => fake()->bothify('Van-##'),
            'equipment_type_id' => fn () => EquipmentType::query()->firstOrCreate(['name' => 'Dry Van'])->id,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }
}

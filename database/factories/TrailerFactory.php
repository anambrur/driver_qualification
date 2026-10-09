<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\EquipmentType;
use App\Models\Trailer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trailer>
 */
class TrailerFactory extends Factory
{
    protected $model = Trailer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'unit_no' => 'TRL-'.fake()->unique()->numerify('#####'),
            'vin' => Str::upper(fake()->unique()->bothify('1JJ??#########??')),
            'year' => (int) fake()->numberBetween(2005, (int) date('Y')),
            'make' => fake()->randomElement(['Utility', 'Wabash', 'Great Dane']),
            'model' => fake()->bothify('Van-##'),
            'equipment_types_id' => fn () => EquipmentType::query()->firstOrCreate(['name' => 'Dry Van'])->id,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }
}

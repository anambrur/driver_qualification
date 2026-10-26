<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Shipper;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipper>
 */
class ShipperFactory extends Factory
{
    use CreatesInTenant;

    protected $model = Shipper::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'name' => fake()->company(),
            'contact_name' => fake()->name(),
            'phone' => fake()->numerify('555-###-####'),
            'email' => fake()->safeEmail(),
            'address_line1' => fake()->streetAddress(),
            'city' => 'Dallas',
            'state_code' => 'TX',
            'zip' => fake()->numerify('75###'),
            'country_code' => 'US',
            'is_active' => true,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

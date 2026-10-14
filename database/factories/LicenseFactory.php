<?php

namespace Database\Factories;

use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * License has no HasFactory trait; use LicenseFactory::new().
 *
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    protected $model = License::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => DriverFactory::new(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'issued' => now()->subYears(3)->toDateString(),
            'expires' => now()->addYears(2)->toDateString(),
            'country_code' => 'US',
            'state_code' => 'TX',
            'class' => 'A',
            'license_number' => fake()->unique()->bothify('########'),
            'is_current' => false,
        ];
    }

    public function current(): static
    {
        return $this->state(fn () => ['is_current' => true]);
    }
}

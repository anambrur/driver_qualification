<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Driver has no HasFactory trait; use DriverFactory::new().
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    protected $model = Driver::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            // Mirrors production: drivers.user_id is the owning company's user.
            'user_id' => fn (array $attributes) => Company::find($attributes['company_id'])->user_id,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-21 years')->format('Y-m-d'),
            'ssn' => fake()->numerify('#########'),
            'main_phone' => '+1202555'.fake()->unique()->numerify('####'),
            'email' => fake()->unique()->safeEmail(),
            'medical_certificate_expiration_date' => now()->addYear()->toDateString(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => 'Texas',
            'country' => 'United States',
            'postal_code' => fake()->postcode(),
            'status' => 'active',
            'source' => 'admin',
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => [
            'company_id' => $company->id,
            'user_id' => $company->user_id,
        ]);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    /**
     * A draft created by the public application OTP step (only phone is known).
     */
    public function publicDraft(): static
    {
        return $this->state(fn () => [
            'first_name' => null,
            'last_name' => null,
            'date_of_birth' => null,
            'ssn' => null,
            'email' => null,
            'status' => 'draft',
            'source' => 'public_application',
        ]);
    }
}

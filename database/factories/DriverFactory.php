<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Driver has no HasFactory trait; use DriverFactory::new().
 *
 * Defaults to a hired driver created by staff (no application). user_id (the driver's own
 * login) stays null unless a test links one.
 *
 * @extends Factory<Driver>
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
            'created_by_user_id' => fn (array $attributes) => Company::find($attributes['company_id'])->owner_user_id,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-21 years')->format('Y-m-d'),
            'ssn' => fake()->numerify('#########'),
            'main_phone' => '+1202555'.fake()->unique()->numerify('####'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state_code' => 'TX',
            'country_code' => 'US',
            'postal_code' => fake()->postcode(),
            'application_status' => null,
            'employment_status' => 'active',
            'source' => 'admin',
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => [
            'company_id' => $company->id,
            'created_by_user_id' => $company->owner_user_id,
        ]);
    }

    /**
     * @param  'applicant'|'active'|'inactive'|'terminated'  $status
     */
    public function employment(string $status): static
    {
        return $this->state(fn () => ['employment_status' => $status]);
    }

    /**
     * A public applicant at the given application stage.
     *
     * @param  'draft'|'pending'|'submitted'|'under_review'|'approved'|'rejected'|'withdrawn'  $status
     */
    public function application(string $status): static
    {
        return $this->state(fn () => [
            'application_status' => $status,
            'employment_status' => 'applicant',
            'source' => 'public_application',
            'created_by_user_id' => null,
        ]);
    }

    public function publicDraft(): static
    {
        return $this->application('draft')->state(fn () => [
            'first_name' => null,
            'last_name' => null,
            'date_of_birth' => null,
            'ssn' => null,
            'email' => null,
        ]);
    }

    /**
     * Adds a current licence expiring on the given date (default: in two years).
     */
    public function withCurrentLicense(?string $expires = null): static
    {
        return $this->afterCreating(fn (Driver $driver) => LicenseFactory::new()->create([
            'driver_id' => $driver->id,
            'expires' => $expires ?? now()->addYears(2)->toDateString(),
            'is_current' => true,
        ]));
    }
}

<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Company has no HasFactory trait; use CompanyFactory::new().
 *
 * Creates the company the way production does: company without owner -> owner user in that
 * company -> owner_user_id. Interim until CompanyProvisioner (WP2) becomes the only path.
 *
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'company_name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state_code' => 'TX',
            'zip' => fake()->postcode(),
            'phone' => '2025550143',
            'status' => 'active',
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Company $company) {
            if ($company->owner_user_id === null) {
                $owner = UserFactory::new()->create(['company_id' => $company->id]);
                $company->forceFill(['owner_user_id' => $owner->id])->save();
            }

            $company->settings()->firstOrCreate([]);
        });
    }

    /**
     * Skips the owner and settings. Call before adding other afterCreating callbacks.
     */
    public function withoutOwner(): static
    {
        return $this->newInstance(['afterCreating' => new Collection]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended', 'status_changed_at' => now()]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => ['status' => 'deactivated', 'status_changed_at' => now()]);
    }
}

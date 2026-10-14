<?php

namespace Database\Factories;

use App\Models\Company;
use App\Services\Company\CompanyProvisioner;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Company has no HasFactory trait; use CompanyFactory::new().
 *
 * Creates companies through CompanyProvisioner, the only path that creates a company.
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

    /**
     * Every factory company is provisioned like production: owner, settings, sequence, system
     * roles and support user. The factory attributes become the company columns.
     *
     * @param  Collection<int, Company>  $results
     */
    protected function store(Collection $results)
    {
        $results->each(function (Company $model) {
            $owner = Arr::except(UserFactory::new()->state(['company_id' => null])->raw(), ['company_id']);
            $company = app(CompanyProvisioner::class)->provision($model->getAttributes(), $owner);

            $model->setRawAttributes($company->getAttributes(), true);
            $model->exists = true;
            $model->wasRecentlyCreated = true;
            $model->setRelation('owner', $company->owner);

            $this->createChildren($model);
        });
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

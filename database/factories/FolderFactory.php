<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Folder;
use App\Models\Trailer;
use App\Models\Vehicle;
use Database\Factories\Concerns\CreatesInTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    use CreatesInTenant;

    protected $model = Folder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'name' => fake()->unique()->words(2, true),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }

    /**
     * The standard folder of a driver, vehicle or trailer.
     */
    public function systemFor(Driver|Vehicle|Trailer $entity): static
    {
        $column = $entity->getForeignKey();

        return $this->state(fn () => [
            'company_id' => $entity->company_id,
            'name' => Folder::systemName($entity),
            'system_key' => Folder::BINDINGS[$column],
            $column => $entity->id,
        ]);
    }

    public function childOf(Folder $parent): static
    {
        return $this->state(fn () => ['company_id' => $parent->company_id, 'parent_id' => $parent->id]
            + array_fill_keys(array_keys(Folder::BINDINGS), null)
            + ($parent->binding() === null ? [] : [$parent->binding()[0] => $parent->binding()[1]]));
    }
}

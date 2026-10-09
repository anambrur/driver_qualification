<?php

namespace Database\Factories;

use App\Models\MaintenanceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenanceCategory>
 */
class MaintenanceCategoryFactory extends Factory
{
    protected $model = MaintenanceCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
        ];
    }
}

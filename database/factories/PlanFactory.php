<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Plan '.Str::upper(Str::random(5));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'is_public' => true,
            'is_active' => true,
            'sort_order' => 0,
            'marketing_bullets' => ['Feature A', 'Feature B'],
        ];
    }

    /**
     * Adds a current monthly price, and a yearly one when $yearly is given.
     */
    public function withPrices(float $monthly = 49.00, ?float $yearly = null): static
    {
        return $this->afterCreating(function (Plan $plan) use ($monthly, $yearly) {
            PlanPriceFactory::new()->for($plan)->create(['interval' => 'month', 'amount' => $monthly]);

            if ($yearly !== null) {
                PlanPriceFactory::new()->for($plan)->create(['interval' => 'year', 'amount' => $yearly]);
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

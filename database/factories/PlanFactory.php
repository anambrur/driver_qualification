<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
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
            'price' => 49.00,
            'currency' => 'USD',
            'billing_cycle' => 'monthly',
            'duration_days' => 30,
            'trial_days' => 0,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
            'features' => ['Feature A', 'Feature B'],
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn () => ['billing_cycle' => 'yearly', 'duration_days' => 365, 'price' => 490.00]);
    }

    public function trial(int $days = 14): static
    {
        return $this->state(fn () => ['billing_cycle' => 'trial', 'price' => 0, 'trial_days' => $days, 'duration_days' => $days]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

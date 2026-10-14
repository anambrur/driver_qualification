<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    protected $model = PlanPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'interval' => 'month',
            'amount' => 49.00,
            'currency' => 'USD',
            'stripe_price_id' => null,
        ];
    }

    public function yearly(float $amount = 490.00): static
    {
        return $this->state(fn () => ['interval' => 'year', 'amount' => $amount]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}

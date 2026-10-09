<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Default state is an accessible (active, paid) Stripe subscription.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => PlanFactory::new(),
            'stripe_subscription_id' => 'sub_test_'.fake()->unique()->bothify('????########'),
            'stripe_status' => 'active',
            'billing_cycle' => 'monthly',
            'amount' => 49.00,
            'currency' => 'USD',
            'current_period_start' => now()->subDays(5),
            'current_period_end' => now()->addDays(25),
            'cancel_at_period_end' => false,
            'ends_at' => null,
            'source' => 'stripe',
        ];
    }

    public function trial(int $daysLeft = 7): static
    {
        return $this->state(fn () => [
            'stripe_subscription_id' => null,
            'stripe_status' => 'trialing',
            'billing_cycle' => 'trial',
            'amount' => 0,
            'trial_ends_at' => now()->addDays($daysLeft),
            'current_period_end' => now()->addDays($daysLeft),
            'cancel_at_period_end' => true,
            'ends_at' => now()->addDays($daysLeft),
            'source' => 'trial',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'stripe_status' => 'canceled',
            'current_period_end' => now()->subDay(),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn () => ['stripe_status' => 'past_due']);
    }

    public function cancelingAtPeriodEnd(): static
    {
        return $this->state(fn (array $attributes) => [
            'cancel_at_period_end' => true,
            'ends_at' => now()->addDays(10),
            'current_period_end' => now()->addDays(10),
        ]);
    }
}

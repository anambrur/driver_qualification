<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'          => 'Free Trial',
                'slug'          => 'trial',
                'description'   => 'Try all features for 14 days, no credit card required.',
                'price'         => 0.00,
                'currency'      => 'USD',
                'billing_cycle' => 'trial',
                'duration_days' => 14,
                'trial_days'    => 14,
                'is_active'     => true,
                'is_featured'   => false,
                'sort_order'    => 0,
                'features'      => [
                    'Access to all features',
                    '14-day trial period',
                    'Email support',
                ],
            ],
            [
                'name'          => 'Starter',
                'slug'          => 'starter',
                'description'   => 'Perfect for individuals and small teams.',
                'price'         => 9.99,
                'currency'      => 'USD',
                'billing_cycle' => 'monthly',
                'duration_days' => 30,
                'trial_days'    => 0,
                'is_active'     => true,
                'is_featured'   => false,
                'sort_order'    => 1,
                'features'      => [
                    'Up to 5 users',
                    'Basic features',
                    'Email support',
                    '5 GB storage',
                ],
            ],
            [
                'name'          => 'Professional',
                'slug'          => 'professional',
                'description'   => 'For growing businesses that need more power.',
                'price'         => 29.99,
                'currency'      => 'USD',
                'billing_cycle' => 'monthly',
                'duration_days' => 30,
                'trial_days'    => 0,
                'is_active'     => true,
                'is_featured'   => true,
                'sort_order'    => 2,
                'features'      => [
                    'Up to 25 users',
                    'All features',
                    'Priority support',
                    '50 GB storage',
                    'Advanced analytics',
                    'API access',
                ],
            ],
            [
                'name'          => 'Enterprise',
                'slug'          => 'enterprise',
                'description'   => 'Custom solutions for large organizations.',
                'price'         => 99.99,
                'currency'      => 'USD',
                'billing_cycle' => 'monthly',
                'duration_days' => 30,
                'trial_days'    => 0,
                'is_active'     => true,
                'is_featured'   => false,
                'max_users'     => null, // unlimited
                'sort_order'    => 3,
                'features'      => [
                    'Unlimited users',
                    'All features',
                    'Dedicated support',
                    'Unlimited storage',
                    'Advanced analytics',
                    'API access',
                    'Custom integrations',
                    'SLA guarantee',
                ],
            ],
            [
                'name'          => 'Lifetime',
                'slug'          => 'lifetime',
                'description'   => 'One-time payment for permanent access.',
                'price'         => 299.00,
                'currency'      => 'USD',
                'billing_cycle' => 'lifetime',
                'duration_days' => 36500, // 100 years placeholder
                'trial_days'    => 0,
                'is_active'     => true,
                'is_featured'   => false,
                'sort_order'    => 4,
                'features'      => [
                    'All features forever',
                    'Lifetime updates',
                    'Priority support',
                    'Unlimited storage',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        $this->command->info('Plans seeded successfully.');
    }
}

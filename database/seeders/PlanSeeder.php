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
                'name' => 'Free Trial',
                'stripe_plan_id' => null,
                'stripe_price_id' => null,
                'slug' => 'trial',
                'description' => 'Try all features for 14 days. No credit card required.',
                'price' => 0.00,
                'currency' => 'USD',
                'billing_cycle' => 'trial',
                'duration_days' => 14,
                'trial_days' => 14,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 0,
                'features' => [
                    'Access to all features',
                    '14-day trial period',
                    'No credit card required',
                    'Email support',
                ],
            ],
            [
                'name' => 'Starter Monthly',
                'stripe_plan_id' => null,
                'stripe_price_id' => null,
                'slug' => 'starter-monthly',
                'description' => 'Perfect for individuals and small teams. Billed monthly.',
                'price' => 29.00,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'duration_days' => 30,
                'trial_days' => 0,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'features' => [
                    'Up to 5 users',
                    'Basic features',
                    'Email support',
                    '5 GB storage',
                ],
            ],
            [
                'name' => 'Starter Yearly',
                'stripe_plan_id' => null,
                'stripe_price_id' => null,
                'slug' => 'starter-yearly',
                'description' => 'Perfect for individuals and small teams. Billed yearly.',
                'price' => 290.00,
                'currency' => 'USD',
                'billing_cycle' => 'yearly',
                'duration_days' => 365,
                'trial_days' => 0,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 2,
                'features' => [
                    'Up to 5 users',
                    'Basic features',
                    'Email support',
                    '5 GB storage',
                    '2 months free vs monthly',
                ],
            ],
            [
                'name' => 'Company Monthly',
                'stripe_plan_id' => null,
                'stripe_price_id' => null,
                'slug' => 'company-monthly',
                'description' => 'For growing businesses that need more power. Billed monthly.',
                'price' => 99.00,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'duration_days' => 30,
                'trial_days' => 0,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 3,
                'features' => [
                    'Up to 25 users',
                    'All features',
                    'Priority support',
                    '50 GB storage',
                    'Advanced analytics',
                    'API access',
                ],
            ],
            [
                'name' => 'Company Yearly',
                'stripe_plan_id' => null,
                'stripe_price_id' => null,
                'slug' => 'company-yearly',
                'description' => 'For growing businesses that need more power. Billed yearly.',
                'price' => 990.00,
                'currency' => 'USD',
                'billing_cycle' => 'yearly',
                'duration_days' => 365,
                'trial_days' => 0,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 4,
                'features' => [
                    'Up to 25 users',
                    'All features',
                    'Priority support',
                    '50 GB storage',
                    'Advanced analytics',
                    'API access',
                    '2 months free vs monthly',
                ],
            ],
            [
                'name' => 'Enterprise Monthly',
                'stripe_plan_id' => null,
                'stripe_price_id' => null,
                'slug' => 'enterprise-monthly',
                'description' => 'Custom solutions for large organizations. Billed monthly.',
                'price' => 149.00,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'duration_days' => 30,
                'trial_days' => 0,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 5,
                'features' => [
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
                'name' => 'Enterprise Yearly',
                'stripe_plan_id' => null,
                'stripe_price_id' => null,
                'slug' => 'enterprise-yearly',
                'description' => 'Custom solutions for large organizations. Billed yearly.',
                'price' => 1490.00,
                'currency' => 'USD',
                'billing_cycle' => 'yearly',
                'duration_days' => 365,
                'trial_days' => 0,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 6,
                'features' => [
                    'Unlimited users',
                    'All features',
                    'Dedicated support',
                    'Unlimited storage',
                    'Advanced analytics',
                    'API access',
                    'Custom integrations',
                    'SLA guarantee',
                    '2 months free vs monthly',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        // Deactivate legacy Cashier-era slugs if present.
        Plan::whereIn('slug', ['starter', 'company', 'enterprise'])
            ->update(['is_active' => false]);

        $this->command?->info('Plans seeded successfully (trial / monthly / yearly).');
    }
}

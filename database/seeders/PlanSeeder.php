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
                'stripe_plan_id' => 'prod_UKS5bfxqHIzkOJ',
                'stripe_price_id' => 'price_1TLnB1G3V8Npq4KlnbBoPUzC',
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
                'stripe_plan_id' => 'prod_UKS6rdpzUrJmtU',
                'stripe_price_id' => 'price_1TLnC0G3V8Npq4KlOIVVsgQS',
                'slug'          => 'starter',
                'description'   => 'Perfect for individuals and small teams.',
                'price'         => 29.00,
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
                'name'          => 'Company',
                'stripe_plan_id' => 'prod_UKS6F6WKmtg2b8',
                'stripe_price_id' => 'price_1TLnCTG3V8Npq4KlZmwdeOOg',
                'slug'          => 'company',
                'description'   => 'For growing businesses that need more power.',
                'price'         => 99,
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
                'stripe_plan_id' => 'prod_UKS7RaiZtl0eYa',
                'stripe_price_id' => 'price_1TLnCqG3V8Npq4Klxe1QqFH5',
                'slug'          => 'enterprise',
                'description'   => 'Custom solutions for large organizations.',
                'price'         => 149,
                'currency'      => 'USD',
                'billing_cycle' => 'monthly',
                'duration_days' => 30,
                'trial_days'    => 0,
                'is_active'     => true,
                'is_featured'   => false,
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
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        $this->command->info('Plans seeded successfully.');
    }
}

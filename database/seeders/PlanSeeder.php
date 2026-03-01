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
                'stripe_plan_id' => 'prod_U3duXFkkE7C9f4',
                'stripe_price_id' => 'price_1T5WcSFeYL7m5keEPgJlhuRz',
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
                'stripe_plan_id' => 'prod_U3du1hpC2zMXds',
                'stripe_price_id' => 'price_1T5WctFeYL7m5keE19O6Pvg7',
                'slug'          => 'starter',
                'description'   => 'Perfect for individuals and small teams.',
                'price'         => 29.99,
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
                'stripe_plan_id' => 'prod_U3dvvncBIHmj5L',
                'stripe_price_id' => 'price_1T5WdBFeYL7m5keEHN2z8NzA',
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
                'stripe_plan_id' => 'prod_U3dvRMLJbBI8EY',
                'stripe_price_id' => 'price_1T5WdPFeYL7m5keEgWL30Wm8',
                'slug'          => 'enterprise',
                'description'   => 'Custom solutions for large organizations.',
                'price'         => 499,
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

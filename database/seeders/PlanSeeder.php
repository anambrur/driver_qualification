<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * Plans are products; prices are immutable rows per interval (07 §3).
 * The trial is a trialing subscription on the plan in site_settings.trial_plan_id (Q5: the top tier).
 * Stripe Price ids are filled by plans:sync-stripe, never here.
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Perfect for individuals and small teams.',
                'sort_order' => 1,
                'prices' => ['month' => 29.00, 'year' => 290.00],
                'marketing_bullets' => ['Up to 5 users', 'Basic features', 'Email support', '5 GB storage'],
            ],
            [
                'name' => 'Company',
                'slug' => 'company',
                'description' => 'For growing businesses that need more power.',
                'sort_order' => 2,
                'prices' => ['month' => 99.00, 'year' => 990.00],
                'marketing_bullets' => ['Up to 25 users', 'All features', 'Priority support', '50 GB storage', 'Advanced analytics', 'API access'],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Custom solutions for large organizations.',
                'sort_order' => 3,
                'prices' => ['month' => 149.00, 'year' => 1490.00],
                'marketing_bullets' => ['Unlimited users', 'All features', 'Dedicated support', 'Unlimited storage', 'Advanced analytics', 'API access', 'Custom integrations', 'SLA guarantee'],
            ],
        ];

        foreach ($plans as $data) {
            $prices = $data['prices'];
            unset($data['prices']);

            $plan = Plan::updateOrCreate(['slug' => $data['slug']], $data + ['is_public' => true, 'is_active' => true]);

            foreach ($prices as $interval => $amount) {
                // Never rewrite a price: only add one when the interval has no current price.
                if (! $plan->prices()->current()->where('interval', $interval)->where('currency', 'USD')->exists()) {
                    $plan->prices()->create(['interval' => $interval, 'amount' => $amount, 'currency' => 'USD']);
                }
            }
        }

        $settings = SiteSetting::query()->first() ?? new SiteSetting(['site_name' => 'My Application']);
        $settings->trial_plan_id ??= Plan::where('slug', 'enterprise')->value('id');
        $settings->save();

        $this->command?->info('Plans and prices seeded.');
    }
}

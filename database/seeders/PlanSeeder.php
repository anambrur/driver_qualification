<?php

namespace Database\Seeders;

use App\Features\Feature;
use App\Models\Plan;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Plans are products; prices are immutable rows per interval (07 §3).
 * The trial is a trialing subscription on the plan in site_settings.trial_plan_id (Q5: the top tier).
 * Stripe Price ids are filled by plans:sync-stripe, never here. Plan features are a starting
 * configuration (05 §2, placeholder amounts): only missing rows are added, so the Platform
 * Owner's edits survive a re-seed.
 */
class PlanSeeder extends Seeder
{
    /** Core and compliance modules (feature key => null: modules have no limit). */
    private const STARTER = [
        'drivers' => null, 'driver_applications' => null, 'driver_compliance' => null, 'fleet' => null,
        'fleet_compliance' => null, 'compliance_reminders' => null, 'zip_export' => null,
    ];

    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'features' => self::STARTER + [
                    'max_users' => 5, 'max_drivers' => 10, 'max_vehicles' => 10, 'max_trailers' => 10, 'storage_gb' => 5,
                ],
                'slug' => 'starter',
                'description' => 'Perfect for individuals and small teams.',
                'sort_order' => 1,
                'prices' => ['month' => 29.00, 'year' => 290.00],
                'marketing_bullets' => ['Up to 5 users', 'Basic features', 'Email support', '5 GB storage'],
            ],
            [
                'name' => 'Company',
                'features' => self::STARTER + ['maintenance' => null, 'trips' => null, 'document_folders' => null] + [
                    'max_users' => 25, 'max_drivers' => 50, 'max_vehicles' => 50, 'max_trailers' => 50, 'storage_gb' => 50,
                ],
                'slug' => 'company',
                'description' => 'For growing businesses that need more power.',
                'sort_order' => 2,
                'prices' => ['month' => 99.00, 'year' => 990.00],
                'marketing_bullets' => ['Up to 25 users', 'All features', 'Priority support', '50 GB storage', 'Advanced analytics', 'API access'],
            ],
            [
                'name' => 'Enterprise',
                'features' => array_fill_keys(array_column(Feature::cases(), 'value'), null),
                'slug' => 'enterprise',
                'description' => 'Custom solutions for large organizations.',
                'sort_order' => 3,
                'prices' => ['month' => 149.00, 'year' => 1490.00],
                'marketing_bullets' => ['Unlimited users', 'All features', 'Dedicated support', 'Unlimited storage', 'Advanced analytics', 'API access', 'Custom integrations', 'SLA guarantee'],
            ],
        ];

        foreach ($plans as $data) {
            $prices = $data['prices'];
            $features = $data['features'];
            unset($data['prices'], $data['features']);

            $plan = Plan::updateOrCreate(['slug' => $data['slug']], $data + ['is_public' => true, 'is_active' => true]);

            foreach ($prices as $interval => $amount) {
                // Never rewrite a price: only add one when the interval has no current price.
                if (! $plan->prices()->current()->where('interval', $interval)->where('currency', 'USD')->exists()) {
                    $plan->prices()->create(['interval' => $interval, 'amount' => $amount, 'currency' => 'USD']);
                }
            }

            DB::table('plan_features')->insertOrIgnore(array_map(fn (string $key, ?int $limit) => [
                'plan_id' => $plan->id, 'feature_key' => $key, 'enabled' => true, 'limit_value' => $limit,
                'created_at' => now(), 'updated_at' => now(),
            ], array_keys($features), $features));
        }

        $settings = SiteSetting::query()->first() ?? new SiteSetting(['site_name' => 'My Application']);
        $settings->trial_plan_id ??= Plan::where('slug', 'enterprise')->value('id');
        $settings->save();

        $this->command?->info('Plans and prices seeded.');
    }
}

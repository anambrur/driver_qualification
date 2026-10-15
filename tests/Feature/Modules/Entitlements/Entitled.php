<?php

namespace Tests\Feature\Modules\Entitlements;

use App\Models\Company;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Services\Entitlements\Entitlements;
use Database\Factories\PlanFactory;
use Database\Factories\PlanPriceFactory;
use Database\Factories\SubscriptionFactory;
use Illuminate\Support\Facades\DB;

/**
 * Fixtures for entitlements (05). Plans are built from feature key => limit (null = unlimited,
 * false = a row with enabled = false).
 */
final class Entitled
{
    /**
     * @param  array<string, int|false|null>  $features
     */
    public static function plan(array $features): PlanPrice
    {
        $price = PlanPriceFactory::new()->for(PlanFactory::new())->create();

        foreach ($features as $key => $limit) {
            DB::table('plan_features')->insert([
                'plan_id' => $price->plan_id, 'feature_key' => $key, 'enabled' => $limit !== false,
                'limit_value' => $limit === false ? null : $limit, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $price;
    }

    /**
     * Ends the company's open subscription and opens an active one on this price, bumping the
     * version as every real subscription change does.
     */
    public static function subscribe(Company $company, PlanPrice $price): Company
    {
        $company->subscriptions()->whereNull('ended_at')->update(['ended_at' => now()]);
        SubscriptionFactory::new()->forCompany($company)->create(['plan_price_id' => $price->id]);
        $company->increment('entitlements_version');

        return $company->fresh();
    }

    public static function planOf(Company $company): Plan
    {
        return $company->subscriptions()->whereNull('ended_at')->firstOrFail()->planPrice->plan;
    }

    /**
     * Resolves as a new request would: fresh company row, empty per-request memos.
     */
    public static function resolve(Company $company): Entitlements
    {
        self::newRequest();

        return Entitlements::for($company->fresh());
    }

    public static function newRequest(): void
    {
        app()->forgetScopedInstances();
    }
}

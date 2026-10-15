<?php

namespace App\Services\Entitlements;

use App\Features\Feature;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Modules a company loses through a plan change stay read-only and exportable (Q3, 05 §7.2).
 * Callers bump entitlements_version in the same transaction.
 */
class RetainedFeatures
{
    /**
     * Records the modules the old price's plan included and the new one does not, and clears
     * the ones the new plan includes again.
     */
    public static function priceChanged(Company $company, ?int $fromPriceId, ?int $toPriceId): void
    {
        $prices = DB::table('plan_prices')->whereIn('id', array_filter([$fromPriceId, $toPriceId]))->pluck('plan_id', 'id');
        $fromPlan = $prices->get($fromPriceId);
        $toPlan = $prices->get($toPriceId);

        if ($fromPlan === null || $toPlan === null || $fromPlan === $toPlan) {
            return;
        }

        $modules = array_map(fn (Feature $feature) => $feature->value, array_filter(Feature::cases(), fn (Feature $feature) => ! $feature->isLimit()));
        $enabled = fn (int $planId) => DB::table('plan_features')
            ->where('plan_id', $planId)->where('enabled', true)->whereIn('feature_key', $modules)
            ->pluck('feature_key')->all();

        $kept = $enabled($toPlan);
        $lost = array_diff($enabled($fromPlan), $kept);

        DB::table('company_retained_features')->where('company_id', $company->id)->whereIn('feature_key', $kept)->delete();
        DB::table('company_retained_features')->insertOrIgnore(array_map(
            fn (string $key) => ['company_id' => $company->id, 'feature_key' => $key, 'retained_at' => now()],
            array_values($lost),
        ));
    }

    /**
     * The price of the company's most recent subscription, open or ended.
     */
    public static function lastPriceId(Company $company): ?int
    {
        return $company->subscriptions()->orderByDesc('id')->value('plan_price_id');
    }
}

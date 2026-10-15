<?php

namespace App\Services\Entitlements;

use App\Features\Feature;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformUser;
use Illuminate\Support\Facades\DB;

/**
 * What a plan includes (05 §3). An edit applies immediately to every company on the plan
 * (Q3): their entitlements_version is bumped in the same transaction.
 */
class PlanFeatureService
{
    public function set(PlatformUser $actor, Plan $plan, Feature $feature, bool $enabled, ?int $limit = null): void
    {
        DB::transaction(function () use ($actor, $plan, $feature, $enabled, $limit) {
            $before = DB::table('plan_features')->where('plan_id', $plan->id)->where('feature_key', $feature->value)->first(['enabled', 'limit_value']);
            $now = now();

            DB::table('plan_features')->upsert(
                [['plan_id' => $plan->id, 'feature_key' => $feature->value, 'enabled' => $enabled, 'limit_value' => $limit, 'created_at' => $now, 'updated_at' => $now]],
                ['plan_id', 'feature_key'],
                ['enabled', 'limit_value', 'updated_at'],
            );

            // Lapsed companies resolve against their last plan, so ended subscriptions count too.
            Company::query()->whereIn('id', DB::table('subscriptions')
                ->join('plan_prices', 'plan_prices.id', '=', 'subscriptions.plan_price_id')
                ->where('plan_prices.plan_id', $plan->id)
                ->select('subscriptions.company_id'))
                ->increment('entitlements_version');

            AuditLog::record($actor, null, 'plan_feature.updated', $plan, [
                'feature' => $feature->value,
                'before' => $before === null ? null : ['enabled' => (bool) $before->enabled, 'limit_value' => $before->limit_value],
                'after' => ['enabled' => $enabled, 'limit_value' => $limit],
            ]);
        });
    }
}

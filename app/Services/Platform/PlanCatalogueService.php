<?php

namespace App\Services\Platform;

use App\Features\Feature;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\PlatformUser;
use App\Services\Entitlements\PlanFeatureService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Plans, prices and feature availability (05, 07 §3). Prices are immutable: a new price for the
 * same plan, interval and currency archives the current one. Plan features go through
 * PlanFeatureService. Every change is audited.
 */
class PlanCatalogueService
{
    public const AVAILABILITY = ['general', 'override_only', 'disabled'];

    public function __construct(private PlanFeatureService $planFeatures) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPlan(PlatformUser $actor, array $attributes): Plan
    {
        return DB::transaction(function () use ($actor, $attributes) {
            $plan = Plan::query()->create($attributes);
            AuditLog::record($actor, null, 'plan.created', $plan, $attributes);

            return $plan;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updatePlan(PlatformUser $actor, Plan $plan, array $attributes): void
    {
        $plan->fill($attributes);
        $changes = $plan->getDirty();

        if ($changes === []) {
            return;
        }

        DB::transaction(function () use ($actor, $plan, $changes) {
            $plan->save();
            AuditLog::record($actor, null, 'plan.updated', $plan, $changes);
        });
    }

    /**
     * Only a plan nobody ever subscribed to or paid for; otherwise deactivate it.
     */
    public function deletePlan(PlatformUser $actor, Plan $plan): void
    {
        $used = DB::table('subscriptions')->whereIn('plan_price_id', $plan->prices()->select('id'))->exists()
            || DB::table('payments')->where('plan_id', $plan->id)->exists()
            || DB::table('site_settings')->where('trial_plan_id', $plan->id)->exists();

        if ($used) {
            throw ValidationException::withMessages(['plan' => 'This plan is in use. Deactivate it instead.']);
        }

        DB::transaction(function () use ($actor, $plan) {
            $plan->prices()->delete();
            $plan->delete();
            AuditLog::record($actor, null, 'plan.deleted', $plan, ['name' => $plan->name, 'slug' => $plan->slug]);
        });
    }

    /**
     * The plan's feature matrix: [feature key => ['enabled' => bool, 'limit_value' => ?int]]. A
     * blank limit means unlimited. Only changed rows are written, each through
     * PlanFeatureService, which bumps the companies on the plan and audits.
     *
     * @param  array<string, array{enabled?: mixed, limit_value?: mixed}>  $submitted
     */
    public function syncFeatures(PlatformUser $actor, Plan $plan, array $submitted): void
    {
        $current = DB::table('plan_features')->where('plan_id', $plan->id)->get()->keyBy('feature_key');

        foreach (Feature::cases() as $feature) {
            $enabled = (bool) ($submitted[$feature->value]['enabled'] ?? false);
            $raw = $submitted[$feature->value]['limit_value'] ?? null;
            $limit = $feature->isLimit() && $raw !== null && $raw !== '' ? (int) $raw : null;
            $row = $current->get($feature->value);

            $changed = $row === null
                ? $enabled
                : (bool) $row->enabled !== $enabled || ($row->limit_value === null ? null : (int) $row->limit_value) !== $limit;

            if ($changed) {
                $this->planFeatures->set($actor, $plan, $feature, $enabled, $limit);
            }
        }
    }

    public function addPrice(PlatformUser $actor, Plan $plan, string $interval, string $amount, string $currency, ?string $stripePriceId): PlanPrice
    {
        return DB::transaction(function () use ($actor, $plan, $interval, $amount, $currency, $stripePriceId) {
            $current = $plan->prices()->current()->where('interval', $interval)->where('currency', $currency)->lockForUpdate()->first();

            if ($current !== null) {
                $this->archive($actor, $current);
            }

            $price = $plan->prices()->create([
                'interval' => $interval,
                'amount' => $amount,
                'currency' => $currency,
                'stripe_price_id' => $stripePriceId,
            ]);

            AuditLog::record($actor, null, 'plan_price.created', $price, [
                'plan_id' => $plan->id, 'interval' => $interval, 'amount' => $amount, 'currency' => $currency,
                'stripe_price_id' => $stripePriceId, 'replaces' => $current?->id,
            ]);

            return $price;
        });
    }

    /**
     * Existing subscriptions keep their price; new checkouts can no longer pick it.
     */
    public function archivePrice(PlatformUser $actor, PlanPrice $price): void
    {
        if ($price->archived_at !== null) {
            return;
        }

        DB::transaction(fn () => $this->archive($actor, $price));
    }

    /**
     * Applies to every company at once (Q3: catalogue edits apply immediately), so every
     * company's entitlements_version is bumped.
     */
    public function setAvailability(PlatformUser $actor, string $featureKey, string $availability): void
    {
        DB::transaction(function () use ($actor, $featureKey, $availability) {
            $from = DB::table('features')->where('key', $featureKey)->lockForUpdate()->value('availability');

            if ($from === null) {
                throw ValidationException::withMessages(['feature' => 'Unknown feature.']);
            }

            if ($from === $availability) {
                return;
            }

            DB::table('features')->where('key', $featureKey)->update(['availability' => $availability, 'updated_at' => now()]);
            Company::query()->increment('entitlements_version');
            AuditLog::record($actor, null, 'feature.updated', null, ['feature' => $featureKey, 'from' => $from, 'to' => $availability]);
        });
    }

    private function archive(PlatformUser $actor, PlanPrice $price): void
    {
        $price->forceFill(['archived_at' => now()])->save();
        AuditLog::record($actor, null, 'plan_price.archived', $price, ['plan_id' => $price->plan_id]);
    }
}

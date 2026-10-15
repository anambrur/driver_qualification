<?php

namespace App\Services\Entitlements;

use App\Features\Feature;
use App\Features\FeatureState;
use App\Models\Company;
use App\Services\Billing\AccessState;
use App\Services\Billing\AccessStateResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a company's entitlements (05 §4 rules 1-7). Cached under the company's
 * entitlements_version and access state, so a version bump or a state change (grace ending,
 * suspension) is seen on the next request. Bound scoped: the memos live for one request, job or
 * command run.
 */
class EntitlementResolver
{
    private const MAX_TTL = 86400;

    /** @var array<string, Entitlements> */
    private array $memo = [];

    /** @var Collection<string, string>|null feature key => availability, read once per request or job */
    private ?Collection $availability = null;

    public function __construct(private AccessStateResolver $access) {}

    public function for(Company $company): Entitlements
    {
        $state = $this->access->for($company);
        $key = self::cacheKey($company, $state);

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $this->memo[$key] = Entitlements::fromArray($cached);
        }

        [$entitlements, $expiresAt] = $this->resolve($company, $state);
        Cache::put($key, $entitlements->toArray(), $expiresAt);

        return $this->memo[$key] = $entitlements;
    }

    public static function cacheKey(Company $company, AccessState $state): string
    {
        return "tenant:{$company->id}:entitlements:{$company->entitlements_version}:{$state->value}";
    }

    /**
     * @return array{0: Entitlements, 1: CarbonInterface}
     */
    private function resolve(Company $company, AccessState $access): array
    {
        $now = Carbon::now();
        $expiresAt = $now->copy()->addSeconds(self::MAX_TTL);

        $availability = $this->availability ??= DB::table('features')->pluck('availability', 'key');
        $planId = $this->planId($company, $access);
        $plan = $planId === null ? collect() : DB::table('plan_features')
            ->where('plan_id', $planId)
            ->get(['feature_key', 'enabled', 'limit_value'])
            ->keyBy('feature_key');
        $overrides = DB::table('company_feature_overrides')
            ->where('company_id', $company->id)
            ->whereNull('revoked_at')
            ->get(['feature_key', 'enabled', 'limit_value', 'is_unlimited', 'starts_at', 'ends_at']);
        $retained = DB::table('company_retained_features')->where('company_id', $company->id)->pluck('feature_key')->flip();

        $effective = [];

        foreach ($overrides as $override) {
            $startsAt = Carbon::parse($override->starts_at);
            $endsAt = $override->ends_at === null ? null : Carbon::parse($override->ends_at);

            // The cache entry lives at most until the next override starts or ends (05 §4).
            foreach ([$startsAt, $endsAt] as $boundary) {
                if ($boundary !== null && $boundary->gt($now) && $boundary->lt($expiresAt)) {
                    $expiresAt = $boundary;
                }
            }

            if ($startsAt->lte($now) && ($endsAt === null || $endsAt->gt($now))) {
                $effective[$override->feature_key] = $override;
            }
        }

        $states = [];
        $limits = [];

        foreach (Feature::cases() as $feature) {
            $key = $feature->value;
            $row = $plan->get($key);
            $planEnabled = $row !== null && (bool) $row->enabled;
            $limit = $row?->limit_value === null ? null : (int) $row->limit_value;
            $override = $effective[$key] ?? null;
            $available = $availability->get($key);

            if ($available === null || $available === 'disabled' || $access === AccessState::Blocked) {
                $enabled = false; // rules 1-2
            } elseif ($override !== null) {
                // Rule 3: non-null override fields replace the plan's; a limit override includes the limit.
                $setsLimit = $override->limit_value !== null || (bool) $override->is_unlimited;
                $enabled = $override->enabled === null ? ($setsLimit || $planEnabled) : (bool) $override->enabled;
                $limit = $override->is_unlimited ? null : ($override->limit_value === null ? $limit : (int) $override->limit_value);
            } else {
                // Rules 4-7: beta features need an override; otherwise the plan row decides.
                $enabled = $available !== 'override_only' && $planEnabled;
            }

            $state = $enabled ? FeatureState::On : FeatureState::Off;

            // A module lost through a plan change stays read-only (Q3); an explicit Off does not.
            if ($state === FeatureState::Off && $override?->enabled === null && $planId !== null
                && $available === 'general' && $retained->has($key)) {
                $state = FeatureState::ReadOnly;
            }

            if ($state === FeatureState::On && $access === AccessState::ReadOnly) {
                $state = FeatureState::ReadOnly;
            }

            $states[$key] = $state;
            $limits[$key] = $limit;
        }

        return [new Entitlements($states, $limits), $expiresAt];
    }

    /**
     * The plan whose features apply: the open subscription's, or the last one's for a lapsed,
     * read-only company. None and blocked have no plan (rules 2 and 5).
     */
    private function planId(Company $company, AccessState $access): ?int
    {
        if (! $access->allowsTenantPages()) {
            return null;
        }

        $priceId = $this->access->openSubscription($company)->plan_price_id
            ?? RetainedFeatures::lastPriceId($company);

        return $priceId === null ? null : DB::table('plan_prices')->where('id', $priceId)->value('plan_id');
    }
}

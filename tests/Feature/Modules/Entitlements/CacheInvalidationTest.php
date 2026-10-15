<?php

use App\Features\Feature;
use App\Features\FeatureState;
use App\Models\AuditLog;
use App\Services\Billing\AccessState;
use App\Services\Entitlements\EntitlementResolver;
use App\Services\Entitlements\Entitlements;
use App\Services\Entitlements\FeatureOverrideService;
use App\Services\Entitlements\PlanFeatureService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Modules\Entitlements\Entitled;
use Tests\Support\Actors;

/*
| 05 §4 caching: tenant:{company}:entitlements:{version}:{access state}. A plan feature edit,
| an override and a subscription change bump companies.entitlements_version; a time-limited
| override expires the entry by itself.
*/

beforeEach(function () {
    $this->staff = Actors::platformOwner();
    $this->price = Entitled::plan(['drivers' => null, 'maintenance' => null]);
    $this->company = Entitled::subscribe(Actors::company(), $this->price);
});

it('serves a warm entry without touching the entitlement tables', function () {
    Entitled::resolve($this->company);
    Entitled::newRequest();
    $company = $this->company->fresh();

    DB::flushQueryLog();
    DB::enableQueryLog();
    Entitlements::for($company);
    $queries = implode("\n", array_column(DB::getQueryLog(), 'query'));
    DB::disableQueryLog();

    expect($queries)->not->toContain('plan_features')
        ->not->toContain('company_feature_overrides')
        ->not->toContain('"features"')
        ->and(Cache::has(EntitlementResolver::cacheKey($company, AccessState::Full)))->toBeTrue();
});

it('resolves once per request however many checks run', function () {
    $company = $this->company->fresh();
    Entitlements::for($company);

    DB::flushQueryLog();
    DB::enableQueryLog();
    foreach (range(1, 20) as $ignored) {
        Entitlements::for($company)->allows(Feature::Maintenance);
    }
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($count)->toBe(0);
});

it('bumps every company on the plan when a plan feature changes, and only those', function () {
    $lapsed = Actors::readOnly(Entitled::subscribe(Actors::company(), $this->price));
    $elsewhere = Entitled::subscribe(Actors::company(), Entitled::plan(['maintenance' => null]));
    $versions = fn () => [$this->company->fresh()->entitlements_version, $lapsed->fresh()->entitlements_version, $elsewhere->fresh()->entitlements_version];
    $before = $versions();

    expect(Entitled::resolve($this->company)->state(Feature::Maintenance))->toBe(FeatureState::On);

    app(PlanFeatureService::class)->set($this->staff, $this->price->plan, Feature::Maintenance, false);

    [$company, $lapsedVersion, $elsewhereVersion] = $versions();
    expect($company)->toBe($before[0] + 1)
        ->and($lapsedVersion)->toBe($before[1] + 1)
        ->and($elsewhereVersion)->toBe($before[2])
        ->and(Entitled::resolve($this->company)->state(Feature::Maintenance))->toBe(FeatureState::Off)
        ->and(Entitled::resolve($elsewhere)->state(Feature::Maintenance))->toBe(FeatureState::On)
        ->and(AuditLog::query()->where('action', 'plan_feature.updated')->value('changes'))
        ->toMatchArray(['feature' => 'maintenance', 'before' => ['enabled' => true, 'limit_value' => null], 'after' => ['enabled' => false, 'limit_value' => null]]);
});

it('keeps overrides append-only: a change revokes the open row and inserts a new one', function () {
    $overrides = app(FeatureOverrideService::class);
    $version = $this->company->fresh()->entitlements_version;

    $overrides->grant($this->staff, $this->company, Feature::Trips, true, null, 'pilot');
    expect(Entitled::resolve($this->company)->state(Feature::Trips))->toBe(FeatureState::On);

    $overrides->grant($this->staff, $this->company, Feature::Trips, false, null, 'pilot over');
    expect(Entitled::resolve($this->company)->state(Feature::Trips))->toBe(FeatureState::Off);

    $overrides->revoke($this->staff, $this->company, Feature::Trips, 'cleanup');
    expect(Entitled::resolve($this->company)->state(Feature::Trips))->toBe(FeatureState::Off);

    $rows = DB::table('company_feature_overrides')->where('company_id', $this->company->id)->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('revoke_reason')->all())->toBe(['replaced', 'cleanup'])
        ->and($rows->whereNull('revoked_at'))->toBeEmpty()
        ->and($this->company->fresh()->entitlements_version)->toBe($version + 3)
        ->and(AuditLog::query()->where('action', 'like', 'feature_override.%')->count())->toBe(3);
});

it('expires the cache entry when a time-limited override ends, without a version bump', function () {
    app(FeatureOverrideService::class)->grant($this->staff, $this->company, Feature::Trips, true, null, 'trial week', endsAt: now()->addHour());
    $version = $this->company->fresh()->entitlements_version;

    expect(Entitled::resolve($this->company)->state(Feature::Trips))->toBe(FeatureState::On);

    $this->travel(61)->minutes();

    expect(Entitled::resolve($this->company)->state(Feature::Trips))->toBe(FeatureState::Off)
        ->and($this->company->fresh()->entitlements_version)->toBe($version);
});

it('switches key when the access state changes, without a version bump', function () {
    expect(Entitled::resolve($this->company)->state(Feature::Drivers))->toBe(FeatureState::On);

    $company = Actors::suspended($this->company);

    expect(Entitled::resolve($company)->state(Feature::Drivers))->toBe(FeatureState::Off);
});

<?php

use App\Features\Feature;
use App\Features\FeatureState;
use App\Services\Billing\SubscriptionAdminService;
use App\Services\Entitlements\FeatureOverrideService;
use App\Services\Entitlements\RetainedFeatures;
use Database\Factories\SubscriptionFactory;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Modules\Entitlements\Entitled;
use Tests\Support\Actors;

/*
| 05 §4 precedence, first matching rule wins: 1 disabled feature, 2 blocked company,
| 3 effective override, 4 override_only, 5 no subscription, 6 plan row, 7 no row.
| A read-only company (07 §4.3) and a module lost through a plan change (Q3) are ReadOnly.
*/

beforeEach(function () {
    $this->staff = Actors::platformOwner();
    $this->overrides = app(FeatureOverrideService::class);
    $this->company = Entitled::subscribe(Actors::company(), Entitled::plan([
        'drivers' => null, 'maintenance' => null, 'trips' => false, 'max_drivers' => 10,
    ]));
});

it('follows the plan row: enabled is on, disabled or missing is off (rules 6-7)', function () {
    $entitlements = Entitled::resolve($this->company);

    expect($entitlements->state(Feature::Drivers))->toBe(FeatureState::On)
        ->and($entitlements->state(Feature::Trips))->toBe(FeatureState::Off)
        ->and($entitlements->state(Feature::Messaging))->toBe(FeatureState::Off)
        ->and($entitlements->limit(Feature::MaxDrivers))->toBe(10)
        ->and($entitlements->limit(Feature::MaxVehicles))->toBe(0);
});

it('turns a disabled feature off for everyone, even with an override (rule 1)', function () {
    $this->overrides->grant($this->staff, $this->company, Feature::Maintenance, true, null, 'beta');
    DB::table('features')->where('key', 'maintenance')->update(['availability' => 'disabled']);
    $this->company->increment('entitlements_version');

    expect(Entitled::resolve($this->company)->state(Feature::Maintenance))->toBe(FeatureState::Off);
});

it('turns everything off for a suspended company (rule 2)', function () {
    $entitlements = Entitled::resolve(Actors::suspended($this->company));

    expect($entitlements->state(Feature::Drivers))->toBe(FeatureState::Off)
        ->and($entitlements->limit(Feature::MaxDrivers))->toBe(0);
});

it('lets an effective override replace the plan, with null fields inheriting (rule 3)', function () {
    $this->overrides->grant($this->staff, $this->company, Feature::Trips, true, null, 'pilot');
    $this->overrides->grant($this->staff, $this->company, Feature::Drivers, false, null, 'dispute');
    $this->overrides->grant($this->staff, $this->company, Feature::MaxDrivers, null, 25, 'growth');
    $this->overrides->grant($this->staff, $this->company, Feature::MaxVehicles, null, null, 'fleet deal', unlimited: true);
    $this->overrides->grant($this->staff, $this->company, Feature::Maintenance, null, null, 'note only');

    $entitlements = Entitled::resolve($this->company);

    expect($entitlements->state(Feature::Trips))->toBe(FeatureState::On)
        ->and($entitlements->state(Feature::Drivers))->toBe(FeatureState::Off)
        ->and($entitlements->limit(Feature::MaxDrivers))->toBe(25)
        ->and($entitlements->limit(Feature::MaxVehicles))->toBeNull()
        ->and($entitlements->state(Feature::Maintenance))->toBe(FeatureState::On);
});

it('ignores an override that has expired or not started yet', function () {
    $this->overrides->grant($this->staff, $this->company, Feature::Trips, true, null, 'later', startsAt: now()->addDay(), endsAt: now()->addDays(2));
    $this->overrides->grant($this->staff, $this->company, Feature::Messaging, true, null, 'past', startsAt: now()->subDays(2), endsAt: now()->subDay());

    $entitlements = Entitled::resolve($this->company);

    expect($entitlements->state(Feature::Trips))->toBe(FeatureState::Off)
        ->and($entitlements->state(Feature::Messaging))->toBe(FeatureState::Off);
});

it('keeps an override_only feature off without an override (rule 4)', function () {
    DB::table('features')->where('key', 'drivers')->update(['availability' => 'override_only']);
    $this->company->increment('entitlements_version');

    expect(Entitled::resolve($this->company)->state(Feature::Drivers))->toBe(FeatureState::Off);

    $this->overrides->grant($this->staff, $this->company, Feature::Drivers, true, null, 'beta tester');

    expect(Entitled::resolve($this->company)->state(Feature::Drivers))->toBe(FeatureState::On);
});

it('turns everything off for a company that never had a subscription (rule 5)', function () {
    $entitlements = Entitled::resolve(Actors::company(subscribed: false));

    expect($entitlements->state(Feature::Drivers))->toBe(FeatureState::Off)
        ->and($entitlements->state(Feature::Maintenance))->toBe(FeatureState::Off);
});

it('makes a lapsed company read-only on its last plan', function () {
    $entitlements = Entitled::resolve(Actors::readOnly($this->company));

    expect($entitlements->state(Feature::Drivers))->toBe(FeatureState::ReadOnly)
        ->and($entitlements->state(Feature::Maintenance))->toBe(FeatureState::ReadOnly)
        ->and($entitlements->state(Feature::Trips))->toBe(FeatureState::Off);
});

it('keeps full access during the grace period', function () {
    $this->company->subscriptions()->whereNull('ended_at')->delete();
    SubscriptionFactory::new()->forCompany($this->company)->pastDue()->create([
        'plan_price_id' => Entitled::plan(['drivers' => null])->id,
        'current_period_start' => now()->subDays(2),
    ]);

    expect(Entitled::resolve($this->company)->state(Feature::Drivers))->toBe(FeatureState::On);
});

it('keeps a module lost through a plan change read-only, and on again once regained', function () {
    $full = Entitled::plan(['drivers' => null, 'maintenance' => null]);
    $basic = Entitled::plan(['drivers' => null]);

    RetainedFeatures::priceChanged($this->company, $full->id, $basic->id);
    $company = Entitled::subscribe($this->company, $basic);

    expect(Entitled::resolve($company)->state(Feature::Maintenance))->toBe(FeatureState::ReadOnly)
        ->and(Entitled::resolve($company)->state(Feature::Trips))->toBe(FeatureState::Off);

    RetainedFeatures::priceChanged($company, $basic->id, $full->id);
    $company = Entitled::subscribe($company, $full);

    expect(DB::table('company_retained_features')->where('company_id', $company->id)->count())->toBe(0)
        ->and(Entitled::resolve($company)->state(Feature::Maintenance))->toBe(FeatureState::On);
});

it('records lost modules when the platform grants a smaller plan after a lapse', function () {
    $company = Actors::readOnly($this->company);

    app(SubscriptionAdminService::class)->grant($this->staff, $company, Entitled::plan(['drivers' => null]), now()->addMonth());

    $entitlements = Entitled::resolve($company);

    expect($entitlements->state(Feature::Drivers))->toBe(FeatureState::On)
        ->and($entitlements->state(Feature::Maintenance))->toBe(FeatureState::ReadOnly)
        ->and($entitlements->state(Feature::Trips))->toBe(FeatureState::Off);
});

it('keeps one company\'s override away from another company', function () {
    $other = Entitled::subscribe(Actors::company(), Entitled::planOf($this->company)->prices()->firstOrFail());

    $this->overrides->grant($this->staff, $this->company, Feature::Trips, true, null, 'pilot');

    expect(Entitled::resolve($this->company)->state(Feature::Trips))->toBe(FeatureState::On)
        ->and(Entitled::resolve($other)->state(Feature::Trips))->toBe(FeatureState::Off);
});

<?php

use App\Models\SiteSetting;
use App\Services\Billing\AccessState;
use App\Services\Billing\AccessStateResolver;
use Database\Factories\SubscriptionFactory;
use Tests\Support\Actors;
use Tests\Support\Perf;

/*
| 07 §4.3 access state, evaluated per company: blocked → none → full / grace / read_only.
| grace_days is 7 (Q2). A lapsed company stays read-only (decided WP6, P3).
*/

function stateOf($company): AccessState
{
    app(AccessStateResolver::class)->forget($company->id);

    return AccessState::for($company->fresh());
}

it('is full for an active paid subscription', function () {
    expect(stateOf(Actors::company()))->toBe(AccessState::Full);
});

it('is none for a company that never had a subscription', function () {
    expect(stateOf(Actors::company(subscribed: false)))->toBe(AccessState::None);
});

it('is none while the first checkout is incomplete', function () {
    $company = Actors::company(subscribed: false);
    SubscriptionFactory::new()->forCompany($company)->create(['status' => 'incomplete']);

    expect(stateOf($company))->toBe(AccessState::None);
});

it('is blocked for a suspended or deactivated company, whatever it pays', function (string $status) {
    $company = Actors::company();
    $company->forceFill(['status' => $status])->save();

    expect(stateOf($company))->toBe(AccessState::Blocked);
})->with(['suspended', 'deactivated']);

it('is grace for the first 7 days after a failed renewal, then read-only', function () {
    $company = Actors::company(subscribed: false);
    $subscription = SubscriptionFactory::new()->forCompany($company)->pastDue()->create([
        'current_period_start' => now()->subDays(6),
        'current_period_end' => now()->addDays(24),
    ]);

    expect(stateOf($company))->toBe(AccessState::Grace);

    $subscription->update(['current_period_start' => now()->subDays(8)]);

    expect(stateOf($company))->toBe(AccessState::ReadOnly);
});

it('follows grace_days from site settings', function () {
    SiteSetting::getSettings()->update(['grace_days' => 10]);
    $company = Actors::company(subscribed: false);
    SubscriptionFactory::new()->forCompany($company)->pastDue()->create(['current_period_start' => now()->subDays(8)]);

    expect(stateOf($company))->toBe(AccessState::Grace);
});

it('is full during a trial and read-only after it', function () {
    $company = Actors::company(subscribed: false);
    $trial = SubscriptionFactory::new()->forCompany($company)->trial(3)->create();

    expect(stateOf($company))->toBe(AccessState::Full);

    $this->travel(4)->days();

    expect(stateOf($company))->toBe(AccessState::ReadOnly);

    $this->artisan('subscriptions:close-expired-trials');

    expect($trial->fresh()->ended_at)->not->toBeNull()
        ->and(stateOf($company))->toBe(AccessState::ReadOnly);
});

it('is read-only for unpaid and for a lapsed company with only ended rows', function () {
    $unpaid = Actors::company(subscribed: false);
    SubscriptionFactory::new()->forCompany($unpaid)->create(['status' => 'unpaid']);

    expect(stateOf($unpaid))->toBe(AccessState::ReadOnly)
        ->and(stateOf(Actors::readOnly(Actors::company())))->toBe(AccessState::ReadOnly);
});

it('keeps full access while a cancellation still has time left', function () {
    $company = Actors::company(subscribed: false);
    SubscriptionFactory::new()->forCompany($company)->cancelingAtPeriodEnd()->create();

    expect(stateOf($company))->toBe(AccessState::Full);

    $this->travel(11)->days();

    expect(stateOf($company))->toBe(AccessState::ReadOnly);
});

it('uses the period end, not the old trial date, for a converted trial (07 §4.4)', function () {
    $company = Actors::company(subscribed: false);
    $subscription = SubscriptionFactory::new()->forCompany($company)->create([
        'status' => 'active',
        'trial_ends_at' => now()->subDays(20),
        'current_period_end' => now()->addDays(10),
        'cancel_at_period_end' => true,
    ]);

    expect($subscription->accessEndsAt()->isSameDay(now()->addDays(10)))->toBeTrue()
        ->and(stateOf($company))->toBe(AccessState::Full);
});

it('resolves the state with at most one query per request', function () {
    $company = Actors::company();
    app(AccessStateResolver::class)->forget($company->id);

    SiteSetting::getSettings(); // cached forever after the first load

    $queries = Perf::countQueries(function () use ($company) {
        AccessState::for($company);
        AccessState::for($company);
    });

    expect($queries)->toBe(1);
});

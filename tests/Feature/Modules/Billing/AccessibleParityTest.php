<?php

use App\Models\Subscription;
use Database\Factories\SubscriptionFactory;

/*
| BIL-02: Subscription::isAccessible() (full or grace, used by AccessState) and
| Subscription::scopeAccessible() (SQL) must agree on every state. 07 §4.4 end dates.
*/

beforeEach(function () {
    $this->freezeSecond();
});

dataset('subscription states', function () {
    $past = fn () => now()->subDays(3);
    $future = fn () => now()->addDays(3);

    return [
        'active, open-ended' => [fn () => ['status' => 'active']],
        'active, period over (Stripe renews)' => [fn () => ['status' => 'active', 'current_period_end' => $past()]],
        'active, canceling, period left' => [fn () => ['status' => 'active', 'cancel_at_period_end' => true, 'ends_at' => $future()]],
        'active, canceling, ended' => [fn () => ['status' => 'active', 'cancel_at_period_end' => true, 'ends_at' => $past(), 'current_period_end' => $future()]],
        'active, ended_at set' => [fn () => ['status' => 'active', 'ended_at' => $past()]],
        'admin grant, running' => [fn () => ['status' => 'active', 'source' => 'admin', 'stripe_subscription_id' => null, 'cancel_at_period_end' => true, 'ends_at' => $future(), 'current_period_end' => $future()]],
        'admin grant, over' => [fn () => ['status' => 'active', 'source' => 'admin', 'stripe_subscription_id' => null, 'cancel_at_period_end' => true, 'ends_at' => $past(), 'current_period_end' => $past()]],
        'admin grant, no cancel flag, over' => [fn () => ['status' => 'active', 'source' => 'admin', 'stripe_subscription_id' => null, 'current_period_end' => $past()]],
        'trial, not expired' => [fn () => ['status' => 'trialing', 'source' => 'trial', 'cancel_at_period_end' => true, 'ends_at' => $future(), 'trial_ends_at' => $future()]],
        'trial, expired' => [fn () => ['status' => 'trialing', 'source' => 'trial', 'cancel_at_period_end' => true, 'ends_at' => $past(), 'trial_ends_at' => $past(), 'current_period_end' => $past()]],
        'trial, expired, flag off' => [fn () => ['status' => 'trialing', 'source' => 'trial', 'cancel_at_period_end' => false, 'ends_at' => null, 'trial_ends_at' => $past(), 'current_period_end' => $future()]],
        'canceled, time left' => [fn () => ['status' => 'canceled', 'ends_at' => $future()]],
        'canceled, ended' => [fn () => ['status' => 'canceled', 'ends_at' => $past(), 'current_period_end' => $past()]],
        'canceled, ends_at past, period end future' => [fn () => ['status' => 'canceled', 'ends_at' => $past(), 'current_period_end' => $future()]],
        'past_due, in grace' => [fn () => ['status' => 'past_due', 'current_period_start' => now()->subDays(2), 'current_period_end' => $future()]],
        'past_due, grace over' => [fn () => ['status' => 'past_due', 'current_period_start' => now()->subDays(8), 'current_period_end' => $future()]],
        'past_due, no dates' => [fn () => ['status' => 'past_due', 'current_period_start' => null, 'current_period_end' => null]],
        'past_due, canceling, period over' => [fn () => ['status' => 'past_due', 'cancel_at_period_end' => true, 'ends_at' => $past(), 'current_period_start' => now()->subDay()]],
        'incomplete' => [fn () => ['status' => 'incomplete']],
        'incomplete_expired' => [fn () => ['status' => 'incomplete_expired']],
        'unpaid, period left' => [fn () => ['status' => 'unpaid', 'current_period_end' => $future()]],
    ];
});

it('agrees with isAccessible()', function (Closure $state) {
    $subscription = SubscriptionFactory::new()->create($state())->fresh();

    $inScope = Subscription::query()->accessible()->whereKey($subscription->id)->exists();

    expect($inScope)->toBe($subscription->isAccessible());
})->with('subscription states');

it('keeps the scope grouped when chained after another where', function () {
    $mine = SubscriptionFactory::new()->create(['status' => 'canceled', 'ends_at' => now()->addDays(3)]);
    SubscriptionFactory::new()->create(['status' => 'canceled', 'ends_at' => now()->addDays(3)]);

    expect(Subscription::where('company_id', $mine->company_id)->accessible()->pluck('id')->all())->toBe([$mine->id]);
});

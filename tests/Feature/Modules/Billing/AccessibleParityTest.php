<?php

use App\Models\Subscription;
use Database\Factories\SubscriptionFactory;

/*
| BIL-02: Subscription::isAccessible() (used by the Subscribed gate) and
| Subscription::scopeAccessible() (SQL) must agree on every state.
*/

beforeEach(function () {
    $this->freezeSecond();
});

dataset('subscription states', function () {
    $past = fn () => now()->subDays(3);
    $future = fn () => now()->addDays(3);

    return [
        'active, open-ended' => [fn () => ['stripe_status' => 'active']],
        'active, canceling, period left' => [fn () => ['stripe_status' => 'active', 'cancel_at_period_end' => true, 'ends_at' => $future()]],
        'active, canceling, ended' => [fn () => ['stripe_status' => 'active', 'cancel_at_period_end' => true, 'ends_at' => $past(), 'current_period_end' => $future()]],
        'active, not canceling, ends_at past' => [fn () => ['stripe_status' => 'active', 'ends_at' => $past()]],
        'trialing trial, not expired' => [fn () => ['stripe_status' => 'trialing', 'billing_cycle' => 'trial', 'cancel_at_period_end' => true, 'ends_at' => $future(), 'trial_ends_at' => $future()]],
        'trialing trial, expired' => [fn () => ['stripe_status' => 'trialing', 'billing_cycle' => 'trial', 'cancel_at_period_end' => true, 'ends_at' => $past(), 'trial_ends_at' => $past(), 'current_period_end' => $past()]],
        'trialing trial, expired, flag off' => [fn () => ['stripe_status' => 'trialing', 'billing_cycle' => 'trial', 'cancel_at_period_end' => false, 'ends_at' => null, 'trial_ends_at' => $past(), 'current_period_end' => $past()]],
        'canceled, grace left' => [fn () => ['stripe_status' => 'canceled', 'ends_at' => $future()]],
        'canceled, ended' => [fn () => ['stripe_status' => 'canceled', 'ends_at' => $past(), 'current_period_end' => $past()]],
        'canceled, ends_at past, period end future' => [fn () => ['stripe_status' => 'canceled', 'ends_at' => $past(), 'current_period_end' => $future()]],
        'past_due, period left' => [fn () => ['stripe_status' => 'past_due', 'current_period_end' => $future()]],
        'past_due, no dates' => [fn () => ['stripe_status' => 'past_due', 'current_period_end' => null, 'ends_at' => null]],
        'past_due, period over' => [fn () => ['stripe_status' => 'past_due', 'current_period_end' => $past()]],
        'past_due, canceling, period left' => [fn () => ['stripe_status' => 'past_due', 'cancel_at_period_end' => true, 'ends_at' => $future()]],
        'incomplete, canceling, period left' => [fn () => ['stripe_status' => 'incomplete', 'cancel_at_period_end' => true, 'ends_at' => $future()]],
        'incomplete' => [fn () => ['stripe_status' => 'incomplete']],
        'incomplete_expired' => [fn () => ['stripe_status' => 'incomplete_expired']],
        'unpaid, period left' => [fn () => ['stripe_status' => 'unpaid', 'current_period_end' => $future()]],
        'paused' => [fn () => ['stripe_status' => 'paused', 'current_period_end' => $future()]],
    ];
});

it('agrees with isAccessible()', function (Closure $state) {
    $subscription = SubscriptionFactory::new()->create($state())->fresh();

    $inScope = Subscription::query()->accessible()->whereKey($subscription->id)->exists();

    expect($inScope)->toBe($subscription->isAccessible());
})->with('subscription states');

it('keeps the scope grouped when chained after another where', function () {
    $mine = SubscriptionFactory::new()->create(['stripe_status' => 'canceled', 'ends_at' => now()->addDays(3)]);
    SubscriptionFactory::new()->create(['stripe_status' => 'canceled', 'ends_at' => now()->addDays(3)]);

    expect(Subscription::where('user_id', $mine->user_id)->accessible()->pluck('id')->all())->toBe([$mine->id]);
});

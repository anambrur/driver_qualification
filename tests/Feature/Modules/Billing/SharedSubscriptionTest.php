<?php

use App\Services\Billing\AccessState;
use App\Services\Billing\AccessStateResolver;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;

/*
| Acceptance (WP6): billing belongs to the company (07 §2). Every user of a company, drivers
| included, shares its one subscription and access state; one webhook changes it for all.
*/

beforeEach(fn () => Notification::fake());

it('lets two users of one company share one subscription (acceptance)', function () {
    $company = Actors::company(subscribed: false);
    $owner = $company->owner;
    $dispatcher = Actors::dispatcher($company);
    $price = Billing::price();
    $stripeSub = Billing::stripeSubscription($company, $price);
    Billing::fakeStripe($stripeSub);

    $this->actingAs($dispatcher)->get(route('profile.edit'))->assertRedirect(route('pricing.plans'));

    Billing::postWebhook($this, 'customer.subscription.created', $stripeSub)->assertOk();
    app(AccessStateResolver::class)->forget($company->id);

    expect($company->subscriptions()->count())->toBe(1)
        ->and(AccessStateResolver::accessibleSubscription($owner->company)?->id)
        ->toBe(AccessStateResolver::accessibleSubscription($dispatcher->company)?->id);
    $this->actingAs($owner)->get(route('profile.edit'))->assertOk();
    $this->actingAs($dispatcher)->get(route('profile.edit'))->assertOk();
});

it('moves every user of the company to read-only when the shared subscription ends', function () {
    $company = Actors::company();
    $driver = Actors::driverUser($company);
    $dispatcher = Actors::dispatcher($company);

    Actors::readOnly($company);

    foreach ([$company->owner, $driver, $dispatcher] as $user) {
        app(AccessStateResolver::class)->forget($company->id);
        expect(AccessState::for($user->fresh()->company))->toBe(AccessState::ReadOnly);
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }
});

it('does not share a subscription across companies', function () {
    $paying = Actors::company();
    $other = Actors::company(subscribed: false);

    expect(AccessState::for($paying))->toBe(AccessState::Full)
        ->and(AccessState::for($other))->toBe(AccessState::None);
    $this->actingAs($other->owner)->get(route('profile.edit'))->assertRedirect(route('pricing.plans'));
});

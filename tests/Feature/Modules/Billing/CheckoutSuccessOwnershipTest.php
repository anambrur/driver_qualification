<?php

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;

/*
| BIL-04 and 07 §6.1: /checkout-success?session_id=… syncs a Checkout Session only when every
| company id it carries is the user's company and the user holds billing.manage.
*/

beforeEach(function () {
    Notification::fake();
    $this->company = Actors::company(subscribed: false);
    $this->attacker = Actors::company(subscribed: false);
    $this->price = Billing::price();
});

it('does not sync another company\'s checkout session', function () {
    $session = Billing::checkoutSession($this->company, $this->price);
    $client = Billing::fakeStripe(Billing::stripeSubscription($this->company, $this->price), Billing::invoice($this->company), $session);
    $client->subscriptions->shouldNotReceive('retrieve');

    $this->actingAs($this->attacker->owner)
        ->get(route('checkout.success', ['session_id' => $session['id']]))
        ->assertOk();

    expect(Subscription::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and($this->attacker->fresh()->stripe_customer_id)->toBeNull();
});

it('does not sync a session whose metadata names another company, even if client_reference_id matches', function () {
    $session = Billing::checkoutSession($this->company, $this->price, [
        'client_reference_id' => (string) $this->attacker->id,
    ]);
    $client = Billing::fakeStripe(Billing::stripeSubscription($this->company, $this->price), Billing::invoice($this->company), $session);
    $client->subscriptions->shouldNotReceive('retrieve');

    $this->actingAs($this->attacker->owner)
        ->get(route('checkout.success', ['session_id' => $session['id']]))
        ->assertOk();

    expect(Subscription::count())->toBe(0)
        ->and(Payment::count())->toBe(0);
});

it('syncs the company\'s own session', function () {
    $session = Billing::checkoutSession($this->company, $this->price);
    Billing::fakeStripe(Billing::stripeSubscription($this->company, $this->price), Billing::invoice($this->company), $session);

    $this->actingAs($this->company->owner)
        ->get(route('checkout.success', ['session_id' => $session['id']]))
        ->assertOk();

    expect($this->company->subscriptions()->count())->toBe(1)
        ->and(Payment::where('company_id', $this->company->id)->count())->toBe(1);
});

it('forbids a company user without billing.manage', function () {
    $session = Billing::checkoutSession($this->company, $this->price);
    Billing::fakeStripe(Billing::stripeSubscription($this->company, $this->price), Billing::invoice($this->company), $session);

    $this->actingAs(Actors::dispatcher($this->company))
        ->get(route('checkout.success', ['session_id' => $session['id']]))
        ->assertForbidden();

    expect(Subscription::count())->toBe(0);
});

it('requires login', function () {
    $this->get(route('checkout.success', ['session_id' => 'cs_x']))->assertRedirect(route('login'));
});

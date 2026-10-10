<?php

use App\Models\Payment;
use App\Models\Subscription;
use Database\Factories\PlanFactory;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;

/*
| BIL-04: /checkout-success?session_id=… must only sync a Checkout Session that belongs to the
| logged-in user.
*/

beforeEach(function () {
    Notification::fake();
    $this->owner = Actors::companyOwner(subscribed: false);
    $this->attacker = Actors::companyOwner(subscribed: false);
    $this->plan = PlanFactory::new()->create();
});

it('does not sync another user\'s checkout session', function () {
    $session = Billing::checkoutSession($this->owner, $this->plan);
    $client = Billing::fakeStripe(Billing::stripeSubscription($this->owner, $this->plan), Billing::invoice(), $session);
    $client->subscriptions->shouldNotReceive('retrieve');

    $this->actingAs($this->attacker)
        ->get(route('checkout.success', ['session_id' => $session['id']]))
        ->assertOk();

    expect(Subscription::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and($this->attacker->fresh()->stripe_id)->toBeNull();
});

it('does not sync a session whose metadata names another user, even if client_reference_id matches', function () {
    $session = Billing::checkoutSession($this->owner, $this->plan, [
        'client_reference_id' => (string) $this->attacker->id,
    ]);
    $client = Billing::fakeStripe(Billing::stripeSubscription($this->owner, $this->plan), Billing::invoice(), $session);
    $client->subscriptions->shouldNotReceive('retrieve');

    $this->actingAs($this->attacker)
        ->get(route('checkout.success', ['session_id' => $session['id']]))
        ->assertOk();

    expect(Subscription::count())->toBe(0)
        ->and(Payment::count())->toBe(0);
});

it('syncs the logged-in user\'s own session', function () {
    $session = Billing::checkoutSession($this->owner, $this->plan);
    Billing::fakeStripe(Billing::stripeSubscription($this->owner, $this->plan), Billing::invoice(), $session);

    $this->actingAs($this->owner)
        ->get(route('checkout.success', ['session_id' => $session['id']]))
        ->assertOk();

    expect(Subscription::where('user_id', $this->owner->id)->count())->toBe(1)
        ->and(Payment::where('user_id', $this->owner->id)->count())->toBe(1);
});

it('requires login', function () {
    $this->get(route('checkout.success', ['session_id' => 'cs_x']))->assertRedirect(route('login'));
});

<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Notifications\SubscriptionActivatedNotification;
use Database\Factories\PlanFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;

/*
| BIL-01: replayed or overlapping Stripe events must not create a second Subscription row
| or send a second "activated" notification.
*/

beforeEach(function () {
    Notification::fake();

    $this->user = Actors::companyOwner(subscribed: false);
    $this->plan = PlanFactory::new()->create();
    $this->stripeSub = Billing::stripeSubscription($this->user, $this->plan);
    $this->session = Billing::checkoutSession($this->user, $this->plan);

    Billing::fakeStripe($this->stripeSub, Billing::invoice());
});

it('creates one subscription and one activation notice when checkout.session.completed and customer.subscription.created both arrive', function () {
    Billing::postWebhook($this, 'checkout.session.completed', $this->session)->assertOk();
    Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub)->assertOk();

    expect(Subscription::where('user_id', $this->user->id)->count())->toBe(1)
        ->and(Payment::where('user_id', $this->user->id)->count())->toBe(1);
    Notification::assertSentToTimes($this->user, SubscriptionActivatedNotification::class, 1);
});

it('creates one subscription when the events arrive in the opposite order', function () {
    Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub)->assertOk();
    Billing::postWebhook($this, 'checkout.session.completed', $this->session)->assertOk();

    expect(Subscription::where('user_id', $this->user->id)->count())->toBe(1);
    Notification::assertSentToTimes($this->user, SubscriptionActivatedNotification::class, 1);
});

it('ignores a replay of the same event id', function () {
    Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_same')->assertOk();
    Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_same')->assertOk();
    Billing::postWebhook($this, 'invoice.paid', Billing::invoice(), 'evt_paid')->assertOk();
    Billing::postWebhook($this, 'invoice.paid', Billing::invoice(), 'evt_paid')->assertOk();

    expect(Subscription::where('user_id', $this->user->id)->count())->toBe(1)
        ->and(Payment::where('user_id', $this->user->id)->count())->toBe(1);
    Notification::assertSentToTimes($this->user, SubscriptionActivatedNotification::class, 1);
});

it('does not double-sync when the success page and the webhook both process the session', function () {
    Billing::fakeStripe($this->stripeSub, Billing::invoice(), $this->session);

    $this->actingAs($this->user)
        ->get(route('checkout.success', ['session_id' => $this->session['id']]))
        ->assertOk();
    Billing::postWebhook($this, 'checkout.session.completed', $this->session)->assertOk();

    expect(Subscription::where('user_id', $this->user->id)->count())->toBe(1)
        ->and(Payment::where('user_id', $this->user->id)->count())->toBe(1);
    Notification::assertSentToTimes($this->user, SubscriptionActivatedNotification::class, 1);
});

it('lets the database reject a second row for the same Stripe subscription (concurrent insert guard)', function () {
    Actors::subscribe($this->user, ['stripe_subscription_id' => 'sub_dupe']);

    expect(fn () => Actors::subscribe($this->user, ['stripe_subscription_id' => 'sub_dupe']))
        ->toThrow(QueryException::class);
});

it('rejects an unsigned webhook', function () {
    $this->postJson(route('stripe.webhook'), ['type' => 'customer.subscription.created'])
        ->assertStatus(400);

    expect(Subscription::count())->toBe(0);
});

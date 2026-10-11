<?php

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Stripe\CheckoutService;
use Database\Factories\PlanFactory;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;

/*
| PO-18 (2026-10-11): the Stripe account is shared with other apps, so this app's webhook gets
| their events too. They carry the other app's user ids in metadata, so an event tagged with
| another app_host is ignored (200, logged). Untagged objects (older ones) are processed.
*/

beforeEach(function () {
    Notification::fake();

    $this->user = Actors::companyOwner(subscribed: false);
    $this->plan = PlanFactory::new()->create();
    $this->ourHost = app(CheckoutService::class)->appHost();
});

it('ignores another app\'s subscription and checkout events', function (string $type) {
    $sub = Billing::stripeSubscription($this->user, $this->plan, ['metadata' => ['app_host' => 'other-app.example']]);
    $session = Billing::checkoutSession($this->user, $this->plan, ['metadata' => ['app_host' => 'other-app.example']]);
    Billing::fakeStripe($sub, Billing::invoice());

    Billing::postWebhook($this, $type, $type === 'checkout.session.completed' ? $session : $sub)->assertOk();

    expect(Subscription::count())->toBe(0)
        ->and(Payment::count())->toBe(0);
})->with(['customer.subscription.created', 'customer.subscription.updated', 'checkout.session.completed']);

it('ignores another app\'s invoice', function () {
    $sub = Billing::stripeSubscription($this->user, $this->plan);
    Billing::fakeStripe($sub, Billing::invoice());
    Billing::postWebhook($this, 'customer.subscription.created', $sub)->assertOk();
    $payments = Payment::count();

    Billing::postWebhook($this, 'invoice.paid', Billing::invoice([
        'id' => 'in_other_app',
        'parent' => ['subscription_details' => ['metadata' => ['app_host' => 'other-app.example']]],
    ]))->assertOk();

    expect(Payment::count())->toBe($payments);
});

it('processes this app\'s tagged events', function () {
    $sub = Billing::stripeSubscription($this->user, $this->plan, ['metadata' => ['app_host' => $this->ourHost]]);
    Billing::fakeStripe($sub, Billing::invoice());

    Billing::postWebhook($this, 'customer.subscription.created', $sub)->assertOk();

    expect(Subscription::where('user_id', $this->user->id)->count())->toBe(1);
});

it('still processes untagged events', function () {
    $sub = Billing::stripeSubscription($this->user, $this->plan);
    Billing::fakeStripe($sub, Billing::invoice());

    Billing::postWebhook($this, 'customer.subscription.created', $sub)->assertOk();

    expect(Subscription::where('user_id', $this->user->id)->count())->toBe(1);
});

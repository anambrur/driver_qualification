<?php

use App\Models\Payment;
use App\Models\StripeEvent;
use App\Models\Subscription;
use App\Services\Stripe\CheckoutService;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;

/*
| PO-18 (2026-10-11): the Stripe account is shared with other apps, so this app's webhook gets
| their events too. They carry the other app's ids in metadata, so an event tagged with another
| app_host is marked ignored (200, logged). Untagged objects (older ones) are processed.
*/

beforeEach(function () {
    Notification::fake();

    $this->company = Actors::company(subscribed: false);
    $this->price = Billing::price();
    $this->ourHost = app(CheckoutService::class)->appHost();
});

it('ignores another app\'s subscription and checkout events', function (string $type) {
    $sub = Billing::stripeSubscription($this->company, $this->price, ['metadata' => ['app_host' => 'other-app.example']]);
    $session = Billing::checkoutSession($this->company, $this->price, ['metadata' => ['app_host' => 'other-app.example']]);
    Billing::fakeStripe($sub, Billing::invoice($this->company));

    Billing::postWebhook($this, $type, $type === 'checkout.session.completed' ? $session : $sub, 'evt_other')->assertOk();

    expect(Subscription::count())->toBe(0)
        ->and(Payment::count())->toBe(0)
        ->and(StripeEvent::where('event_id', 'evt_other')->value('status'))->toBe('ignored');
})->with(['customer.subscription.created', 'customer.subscription.updated', 'checkout.session.completed']);

it('ignores another app\'s invoice', function () {
    $sub = Billing::stripeSubscription($this->company, $this->price);
    Billing::fakeStripe($sub, Billing::invoice($this->company));
    Billing::postWebhook($this, 'customer.subscription.created', $sub)->assertOk();
    $payments = Payment::count();

    Billing::postWebhook($this, 'invoice.paid', Billing::invoice($this->company, [
        'id' => 'in_other_app',
        'parent' => ['subscription_details' => ['metadata' => ['app_host' => 'other-app.example']]],
    ]))->assertOk();

    expect(Payment::count())->toBe($payments);
});

it('processes this app\'s tagged events', function () {
    $sub = Billing::stripeSubscription($this->company, $this->price, ['metadata' => ['app_host' => $this->ourHost]]);
    Billing::fakeStripe($sub, Billing::invoice($this->company));

    Billing::postWebhook($this, 'customer.subscription.created', $sub)->assertOk();

    expect($this->company->subscriptions()->count())->toBe(1);
});

it('still processes untagged events', function () {
    $sub = Billing::stripeSubscription($this->company, $this->price);
    Billing::fakeStripe($sub, Billing::invoice($this->company));

    Billing::postWebhook($this, 'customer.subscription.created', $sub)->assertOk();

    expect($this->company->subscriptions()->count())->toBe(1);
});

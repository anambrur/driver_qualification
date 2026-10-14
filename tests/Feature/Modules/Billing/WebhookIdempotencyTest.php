<?php

use App\Jobs\ProcessStripeEvent;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\StripeEvent;
use App\Models\Subscription;
use App\Notifications\SubscriptionActivatedNotification;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Modules\Billing\Billing;
use Tests\Support\Actors;

/*
| BIL-01 and 07 §6.2: every signed event is stored once in stripe_events and applied by
| ProcessStripeEvent. Replays and overlapping events never create a second row or notice, and
| an event older than the last one applied never rolls the subscription back.
*/

beforeEach(function () {
    Notification::fake();

    $this->company = Actors::company(subscribed: false);
    $this->price = Billing::price();
    $this->stripeSub = Billing::stripeSubscription($this->company, $this->price);
    $this->session = Billing::checkoutSession($this->company, $this->price);

    Billing::fakeStripe($this->stripeSub, Billing::invoice($this->company));
});

describe('inbox', function () {
    it('stores the event, answers 200 and queues the job on the billing queue', function () {
        Queue::fake();

        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_one')->assertOk();

        $event = StripeEvent::where('event_id', 'evt_one')->sole();
        expect($event->status)->toBe('pending')
            ->and($event->type)->toBe('customer.subscription.created')
            ->and(Subscription::count())->toBe(0);
        Queue::assertPushedOn('billing', ProcessStripeEvent::class, fn ($job) => $job->stripeEventId === $event->id);
    });

    it('does not queue a duplicate event id', function () {
        Queue::fake();

        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_same')->assertOk();
        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_same')->assertOk();

        expect(StripeEvent::count())->toBe(1);
        Queue::assertPushed(ProcessStripeEvent::class, 1);
    });

    it('marks the event processed and audits the change as Stripe', function () {
        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_audit')->assertOk();

        expect(StripeEvent::where('event_id', 'evt_audit')->value('status'))->toBe('processed')
            ->and(AuditLog::where('actor_type', 'stripe')->where('company_id', $this->company->id)->exists())->toBeTrue()
            ->and($this->company->fresh()->entitlements_version)->toBeGreaterThan(1);
    });

    it('rejects an unsigned webhook', function () {
        $this->postJson(route('stripe.webhook'), ['type' => 'customer.subscription.created'])
            ->assertStatus(400);

        expect(StripeEvent::count())->toBe(0);
    });
});

describe('idempotency', function () {
    it('creates one subscription and one activation notice when checkout.session.completed and customer.subscription.created both arrive', function () {
        Billing::postWebhook($this, 'checkout.session.completed', $this->session)->assertOk();
        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub)->assertOk();

        expect($this->company->subscriptions()->count())->toBe(1)
            ->and(Payment::where('company_id', $this->company->id)->count())->toBe(1);
        Notification::assertSentToTimes($this->company->owner, SubscriptionActivatedNotification::class, 1);
    });

    it('creates one subscription when the events arrive in the opposite order', function () {
        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub)->assertOk();
        Billing::postWebhook($this, 'checkout.session.completed', $this->session)->assertOk();

        expect($this->company->subscriptions()->count())->toBe(1);
        Notification::assertSentToTimes($this->company->owner, SubscriptionActivatedNotification::class, 1);
    });

    it('applies a replay of the same event id once', function () {
        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_same')->assertOk();
        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_same')->assertOk();
        Billing::postWebhook($this, 'invoice.paid', Billing::invoice($this->company), 'evt_paid')->assertOk();
        Billing::postWebhook($this, 'invoice.paid', Billing::invoice($this->company), 'evt_paid')->assertOk();

        expect($this->company->subscriptions()->count())->toBe(1)
            ->and(Payment::where('company_id', $this->company->id)->count())->toBe(1);
        Notification::assertSentToTimes($this->company->owner, SubscriptionActivatedNotification::class, 1);
    });

    it('does not double-sync when the success page and the webhook both process the session', function () {
        Billing::fakeStripe($this->stripeSub, Billing::invoice($this->company), $this->session);

        $this->actingAs($this->company->owner)
            ->get(route('checkout.success', ['session_id' => $this->session['id']]))
            ->assertOk();
        Billing::postWebhook($this, 'checkout.session.completed', $this->session)->assertOk();

        expect($this->company->subscriptions()->count())->toBe(1)
            ->and(Payment::where('company_id', $this->company->id)->count())->toBe(1);
        Notification::assertSentToTimes($this->company->owner, SubscriptionActivatedNotification::class, 1);
    });

    it('lets the database reject a second row for the same Stripe subscription', function () {
        SubscriptionFactory::new()->forCompany($this->company)->create(['stripe_subscription_id' => 'sub_dupe']);

        expect(fn () => SubscriptionFactory::new()->forCompany(Actors::company(subscribed: false))->create(['stripe_subscription_id' => 'sub_dupe']))
            ->toThrow(QueryException::class);
    });
});

describe('ordering guard', function () {
    it('ignores an update older than the last applied event', function () {
        $now = now()->timestamp;
        Billing::postWebhook($this, 'customer.subscription.updated', Billing::stripeSubscription($this->company, $this->price, ['status' => 'past_due']), 'evt_new', $now)->assertOk();
        Billing::postWebhook($this, 'customer.subscription.updated', $this->stripeSub, 'evt_old', $now - 60)->assertOk();

        expect($this->company->subscriptions()->sole()->status)->toBe('past_due')
            ->and(StripeEvent::where('event_id', 'evt_old')->value('status'))->toBe('ignored');
    });

    it('applies a newer event after an older one', function () {
        $now = now()->timestamp;
        Billing::postWebhook($this, 'customer.subscription.updated', Billing::stripeSubscription($this->company, $this->price, ['status' => 'past_due']), 'evt_old', $now - 60)->assertOk();
        Billing::postWebhook($this, 'customer.subscription.updated', $this->stripeSub, 'evt_new', $now)->assertOk();

        expect($this->company->subscriptions()->sole()->status)->toBe('active');
    });

    it('records a stale failed invoice without moving the subscription back to past_due', function () {
        $now = now()->timestamp;
        Billing::postWebhook($this, 'customer.subscription.updated', $this->stripeSub, 'evt_new', $now)->assertOk();
        Billing::postWebhook($this, 'invoice.payment_failed', Billing::invoice($this->company, ['id' => 'in_failed']), 'evt_old_fail', $now - 60)->assertOk();

        expect($this->company->subscriptions()->sole()->status)->toBe('active')
            ->and(Payment::where('stripe_invoice_id', 'in_failed')->value('status'))->toBe('failed');
    });
});

describe('company resolution', function () {
    it('refuses an event whose Stripe customer belongs to another company', function () {
        $other = Actors::company(subscribed: false);
        $other->forceFill(['stripe_customer_id' => Billing::customerId($this->company)])->save();

        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_cross')->assertOk();

        expect(Subscription::count())->toBe(0)
            ->and(StripeEvent::where('event_id', 'evt_cross')->value('status'))->toBe('failed');
    });

    it('finds the company by its Stripe customer when metadata has no company_id', function () {
        $this->company->forceFill(['stripe_customer_id' => Billing::customerId($this->company)])->save();
        $sub = $this->stripeSub;
        unset($sub['metadata']['company_id']);

        Billing::postWebhook($this, 'customer.subscription.created', $sub)->assertOk();

        expect($this->company->subscriptions()->count())->toBe(1);
    });

    it('ignores an event for no known company', function () {
        $sub = $this->stripeSub;
        unset($sub['metadata']['company_id']);

        Billing::postWebhook($this, 'customer.subscription.created', $sub, 'evt_nobody')->assertOk();

        expect(Subscription::count())->toBe(0)
            ->and(StripeEvent::where('event_id', 'evt_nobody')->value('status'))->toBe('ignored');
    });
});

describe('prices', function () {
    it('flags an unknown Stripe price for review and keeps the checkout price', function () {
        $sub = Billing::stripeSubscription($this->company, $this->price, ['items' => ['data' => [['price' => ['id' => 'price_dashboard_only']]]]]);

        Billing::postWebhook($this, 'customer.subscription.created', $sub)->assertOk();

        $row = $this->company->subscriptions()->sole();
        expect($row->needs_review)->toBeTrue()
            ->and($row->plan_price_id)->toBe($this->price->id)
            ->and($row->stripe_price_id)->toBe('price_dashboard_only');
    });

    it('maps the Stripe price to plan_prices', function () {
        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub)->assertOk();

        $row = $this->company->subscriptions()->sole();
        expect($row->needs_review)->toBeFalse()
            ->and($row->plan_price_id)->toBe($this->price->id)
            ->and((float) $row->amount)->toBe(49.0);
    });
});

it('re-queues failed events', function () {
    Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_retry')->assertOk();
    StripeEvent::where('event_id', 'evt_retry')->update(['status' => 'failed', 'error' => 'boom']);
    Queue::fake();

    $this->artisan('stripe:requeue', ['--failed' => true])->assertSuccessful();

    expect(StripeEvent::where('event_id', 'evt_retry')->value('status'))->toBe('pending');
    Queue::assertPushed(ProcessStripeEvent::class, 1);
});

/*
| Review regressions (WP6 code review): late, redelivered and same-second events.
*/
describe('late and same-second events', function () {
    it('does not let a late payment_failed overwrite a paid invoice', function () {
        $this->company->forceFill(['stripe_customer_id' => Billing::customerId($this->company)])->save();
        $now = now()->timestamp;
        Billing::postWebhook($this, 'invoice.paid', Billing::invoice($this->company, ['id' => 'in_retry']), 'evt_paid', $now)->assertOk();
        Billing::postWebhook($this, 'invoice.payment_failed', Billing::invoice($this->company, ['id' => 'in_retry']), 'evt_failed', $now - 60)->assertOk();

        $payment = Payment::where('stripe_invoice_id', 'in_retry')->sole();
        expect($payment->status)->toBe('paid')
            ->and($payment->paid_at)->not->toBeNull();
    });

    it('keeps a paying company active when an older incomplete event arrives after the success page', function () {
        Billing::fakeStripe($this->stripeSub, Billing::invoice($this->company), $this->session);
        $this->actingAs($this->company->owner)->get(route('checkout.success', ['session_id' => $this->session['id']]))->assertOk();

        $incomplete = Billing::stripeSubscription($this->company, $this->price, ['status' => 'incomplete']);
        Billing::postWebhook($this, 'customer.subscription.created', $incomplete, 'evt_incomplete', now()->subMinute()->timestamp)->assertOk();

        expect($this->company->subscriptions()->sole()->status)->toBe('active')
            ->and(StripeEvent::where('event_id', 'evt_incomplete')->value('status'))->toBe('ignored');
    });

    it('never reopens a canceled subscription with a same-second update', function () {
        $now = now()->timestamp;
        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_created', $now - 60)->assertOk();
        Billing::postWebhook($this, 'customer.subscription.deleted', Billing::stripeSubscription($this->company, $this->price, ['status' => 'canceled', 'ended_at' => $now]), 'evt_deleted', $now)->assertOk();
        Billing::postWebhook($this, 'customer.subscription.updated', $this->stripeSub, 'evt_same_second', $now)->assertOk();

        $row = $this->company->subscriptions()->sole();
        expect($row->status)->toBe('canceled')
            ->and($row->ended_at)->not->toBeNull()
            ->and($this->company->subscriptions()->open()->exists())->toBeFalse();
    });

    it('queues a stored event again when Stripe retries after a failed dispatch', function () {
        Queue::fake();
        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_lost')->assertOk();
        // The first job never ran (dispatch failed after the insert): its unique lock is gone.
        Cache::lock('laravel_unique_job:'.ProcessStripeEvent::class.':'.StripeEvent::where('event_id', 'evt_lost')->value('id'))->forceRelease();

        Billing::postWebhook($this, 'customer.subscription.created', $this->stripeSub, 'evt_lost')->assertOk();

        Queue::assertPushed(ProcessStripeEvent::class, 2);
    });

    it('refuses a failed invoice whose subscription belongs to another company', function () {
        $this->company->forceFill(['stripe_customer_id' => Billing::customerId($this->company)])->save();
        $other = Actors::company(subscribed: false);
        SubscriptionFactory::new()->forCompany($other)->create(['stripe_subscription_id' => 'sub_test_'.$this->company->id]);

        Billing::postWebhook($this, 'invoice.payment_failed', Billing::invoice($this->company), 'evt_cross_invoice')->assertOk();

        expect(StripeEvent::where('event_id', 'evt_cross_invoice')->value('status'))->toBe('failed')
            ->and($other->subscriptions()->sole()->status)->toBe('active');
    });
});

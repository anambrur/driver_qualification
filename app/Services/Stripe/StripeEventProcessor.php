<?php

namespace App\Services\Stripe;

use App\Models\Company;
use App\Models\StripeEvent;
use App\Models\Subscription;
use App\Services\Billing\SubscriptionNotificationService;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\StripeObject;
use Stripe\Webhook;
use Throwable;

/**
 * Webhook inbox (07 §6.2): the controller stores each signed event once; ProcessStripeEvent
 * applies it here, per company, under the company lock and the ordering guard.
 */
class StripeEventProcessor
{
    public const HANDLED = [
        'checkout.session.completed',
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'invoice.paid',
        'invoice.payment_failed',
    ];

    public function __construct(
        private readonly StripeClientFactory $stripe,
        private readonly CheckoutService $checkout,
        private readonly SubscriptionSync $sync,
        private readonly SubscriptionNotificationService $notifications,
    ) {}

    public function constructEvent(string $payload, string $signature): Event
    {
        $secret = config('services.stripe.webhook_secret');

        if (! $secret) {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        return Webhook::constructEvent($payload, $signature, $secret);
    }

    /**
     * Stores the event once. Returns the row to queue: a new one, or a stored one still pending
     * because its first dispatch failed. Null for a duplicate that is already handled.
     */
    public function receive(Event $event): ?StripeEvent
    {
        $inserted = StripeEvent::query()->insertOrIgnore([
            'event_id' => $event->id,
            'type' => $event->type,
            'stripe_created_at' => isset($event->created) ? Carbon::createFromTimestamp($event->created) : null,
            'payload' => json_encode($event->toArray(), JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'received_at' => now(),
        ]);

        $row = StripeEvent::query()->where('event_id', $event->id)->first();

        return $inserted || $row?->status === 'pending' ? $row : null;
    }

    public function process(StripeEvent $row): void
    {
        if (! in_array($row->status, ['pending', 'failed'], true)) {
            return;
        }

        if (! in_array($row->type, self::HANDLED, true)) {
            $row->finish('ignored', 'Unhandled event type.');

            return;
        }

        $object = Event::constructFrom($row->payload)->data->object;
        $data = $object->toArray();

        // The Stripe account is shared (PO-18): another app's objects carry its own ids.
        $host = $this->appHostOf($data);
        if ($host !== null && $host !== $this->checkout->appHost()) {
            $row->finish('ignored', "Another app's event ({$host}).");

            return;
        }

        try {
            $company = $this->companyFor($data);

            if (! $company) {
                $row->finish('ignored', 'No company for this event.');

                return;
            }

            [$status, $note, $notify] = DB::transaction(fn () => $this->apply($row, $company, $object));
            $notify?->__invoke();
            $row->finish($status, $note);
        } catch (StripeEventRejected $e) {
            Log::warning('Stripe event rejected', ['event_id' => $row->event_id, 'error' => $e->getMessage()]);
            $row->finish('failed', $e->getMessage());
        } catch (Throwable $e) {
            $row->finish('failed', class_basename($e).': '.$e->getMessage());

            throw $e;
        }
    }

    /**
     * The success page's dual write (07 §6.1): the same sync as the webhook, under the lock.
     */
    public function syncCheckoutSession(Company $company, object $session): ?Subscription
    {
        [$subscription, $notify] = DB::transaction(function () use ($company, $session) {
            $this->lock($company);
            $subscription = $this->sync->syncCheckoutSession($company, $session);

            return [$subscription, $this->activated($subscription)];
        });

        $notify?->__invoke();

        return $subscription;
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?Closure}
     */
    private function apply(StripeEvent $row, Company $company, StripeObject $object): array
    {
        $this->lock($company);
        $eventAt = $row->stripe_created_at;

        $subscriptionId = match ($row->type) {
            'checkout.session.completed' => $this->sync->idOf($object->subscription ?? null),
            'invoice.paid', 'invoice.payment_failed' => $this->sync->invoiceSubscriptionId($object),
            default => $object->id,
        };
        $local = $subscriptionId
            ? Subscription::query()->where('stripe_subscription_id', $subscriptionId)->first()
            : null;

        if ($local && $local->company_id !== $company->id) {
            throw new StripeEventRejected("Stripe subscription {$subscriptionId} belongs to another company.");
        }

        // Ordering guard: an event older than the last one applied must not roll the row back.
        $stale = $eventAt && $local?->last_stripe_event_at && $eventAt->lt($local->last_stripe_event_at);

        switch ($row->type) {
            case 'checkout.session.completed':
                return ['processed', null, $this->activated($this->sync->syncCheckoutSession($company, $object))];

            case 'invoice.paid':
                // The live subscription, not the payload: it is at least as new as this event.
                $subscription = $subscriptionId
                    ? $this->sync->upsert($company, $this->stripe->make()->subscriptions->retrieve($subscriptionId), now())
                    : null;
                $this->sync->recordInvoice($company, $object, 'paid');

                return ['processed', null, $this->activated($subscription)];

            case 'invoice.payment_failed':
                $this->sync->recordInvoice($company, $object, 'failed');

                if (! $local || $stale) {
                    return ['processed', $stale ? 'Payment recorded; status older than the last applied event.' : null, null];
                }

                $this->sync->markPastDue($local, $eventAt ?? now());
                $invoiceId = (string) $object->id;
                $url = $object->hosted_invoice_url ?? null;

                return ['processed', null, fn () => $this->notifications->sendPaymentFailed($local, $invoiceId, $url)];

            default:
                if ($stale) {
                    return ['ignored', 'Older than the last applied event.', null];
                }

                $subscription = $this->sync->upsert($company, $object, $eventAt);

                if ($row->type === 'customer.subscription.deleted') {
                    return ['processed', null, $subscription ? fn () => $this->notifications->sendCanceled($subscription) : null];
                }

                return ['processed', null, $this->activated($subscription)];
        }
    }

    private function activated(?Subscription $subscription): ?Closure
    {
        return $subscription?->isAccessible()
            ? fn () => $this->notifications->sendActivated($subscription)
            : null;
    }

    /**
     * Serialises all billing writes of one company (07 §6.2).
     */
    private function lock(Company $company): void
    {
        Company::query()->whereKey($company->id)->lockForUpdate()->first();
    }

    /**
     * The company from metadata.company_id, otherwise from the Stripe customer. Both present and
     * different is refused: the event would move billing between tenants.
     *
     * @param  array<string, mixed>  $data
     */
    private function companyFor(array $data): ?Company
    {
        $metaId = data_get($data, 'metadata.company_id')
            ?? data_get($data, 'subscription_details.metadata.company_id')
            ?? data_get($data, 'parent.subscription_details.metadata.company_id');
        $customerId = $this->sync->idOf(is_array($data['customer'] ?? null) ? (object) $data['customer'] : ($data['customer'] ?? null));

        if (($data['object'] ?? null) === 'checkout.session' && isset($data['client_reference_id'])
            && $metaId !== null && (string) $data['client_reference_id'] !== (string) $metaId) {
            throw new StripeEventRejected('Checkout session client_reference_id and metadata.company_id differ.');
        }

        $byMeta = is_numeric($metaId) ? Company::query()->find((int) $metaId) : null;
        $byCustomer = $customerId ? Company::query()->where('stripe_customer_id', $customerId)->first() : null;

        if ($byMeta && $customerId && ($byCustomer ? ! $byMeta->is($byCustomer) : $byMeta->stripe_customer_id !== null && $byMeta->stripe_customer_id !== $customerId)) {
            throw new StripeEventRejected("Stripe customer {$customerId} does not belong to company {$byMeta->id}.");
        }

        if ($byMeta && $customerId && $byMeta->stripe_customer_id === null) {
            $byMeta->forceFill(['stripe_customer_id' => $customerId])->save();
        }

        return $byMeta ?? $byCustomer;
    }

    /**
     * The app_host CheckoutService tagged the object with: on the object itself (checkout
     * session, subscription) or, for an invoice, on its subscription's metadata.
     *
     * @param  array<string, mixed>  $data
     */
    private function appHostOf(array $data): ?string
    {
        foreach (['metadata.app_host', 'subscription_details.metadata.app_host', 'parent.subscription_details.metadata.app_host'] as $path) {
            $host = data_get($data, $path);

            if (is_string($host) && $host !== '') {
                return $host;
            }
        }

        return null;
    }
}

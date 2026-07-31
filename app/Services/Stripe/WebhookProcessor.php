<?php

namespace App\Services\Stripe;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Webhook;

class WebhookProcessor
{
    public function __construct(
        private readonly StripeClientFactory $stripe,
        private readonly SubscriptionService $subscriptions
    ) {}

    public function constructEvent(string $payload, string $signature): Event
    {
        $secret = config('services.stripe.webhook_secret');

        if (! $secret) {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        return Webhook::constructEvent($payload, $signature, $secret);
    }

    public function handle(Event $event): void
    {
        Log::info('Stripe webhook received', ['type' => $event->type, 'id' => $event->id]);

        match ($event->type) {
            'checkout.session.completed' => $this->syncCheckoutSession($event->data->object),
            'customer.subscription.created',
            'customer.subscription.updated' => $this->handleSubscriptionUpsert($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            'invoice.paid' => $this->recordPaidInvoice($event->data->object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
            default => Log::debug('Unhandled Stripe webhook event', ['type' => $event->type]),
        };
    }

    /**
     * Dual-write entry: sync subscription + first paid invoice from Checkout Session.
     * Used by webhook and /checkout-success (works on localhost without webhook forwarding).
     */
    public function syncCheckoutSession(object $session): void
    {
        $userId = (int) ($session->metadata->user_id ?? $session->client_reference_id ?? 0);
        $user = $userId ? User::find($userId) : null;

        if ($user && ! empty($session->customer) && $user->stripe_id !== $session->customer) {
            $user->forceFill(['stripe_id' => $session->customer])->save();
        }

        $subscriptionId = is_string($session->subscription ?? null)
            ? $session->subscription
            : ($session->subscription->id ?? null);

        if ($subscriptionId) {
            try {
                $stripeSub = $this->stripe->make()->subscriptions->retrieve($subscriptionId, [
                    'expand' => ['latest_invoice'],
                ]);
                $this->handleSubscriptionUpsert($stripeSub, [
                    'user_id' => $session->metadata->user_id ?? null,
                    'plan_id' => $session->metadata->plan_id ?? null,
                    'plan_slug' => $session->metadata->plan_slug ?? null,
                    'billing_cycle' => $session->metadata->billing_cycle ?? null,
                    'checkout_session_id' => $session->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to sync subscription after checkout session', [
                    'session_id' => $session->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->syncPaymentFromCheckoutSession($session);
    }

    /**
     * Record payment from a completed Checkout Session (success page / webhook).
     */
    public function syncPaymentFromCheckoutSession(object $session): ?Payment
    {
        $paymentStatus = $session->payment_status ?? null;
        if (! in_array($paymentStatus, ['paid', 'no_payment_required'], true)
            && ($session->status ?? null) !== 'complete') {
            Log::debug('Checkout session not paid yet; skipping payment sync', [
                'session_id' => $session->id ?? null,
                'payment_status' => $paymentStatus,
            ]);

            return null;
        }

        $checkoutSessionId = $session->id ?? null;
        $invoice = null;

        try {
            $client = $this->stripe->make();

            $invoiceId = is_string($session->invoice ?? null)
                ? $session->invoice
                : ($session->invoice->id ?? null);

            if ($invoiceId) {
                $invoice = $client->invoices->retrieve($invoiceId);
            } elseif (! empty($session->subscription)) {
                $subscriptionId = is_string($session->subscription)
                    ? $session->subscription
                    : ($session->subscription->id ?? null);

                if ($subscriptionId) {
                    $invoices = $client->invoices->all([
                        'subscription' => $subscriptionId,
                        'limit' => 5,
                        'status' => 'paid',
                    ]);
                    $invoice = $invoices->data[0] ?? null;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed retrieving invoice for checkout session', [
                'session_id' => $checkoutSessionId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $invoice) {
            Log::warning('No paid invoice found for checkout session', [
                'session_id' => $checkoutSessionId,
            ]);

            return null;
        }

        return $this->recordPaidInvoice($invoice, $checkoutSessionId);
    }

    /**
     * Idempotent paid-invoice → payments row. Used by webhook, checkout success, and backfill.
     */
    public function recordPaidInvoice(object $invoice, ?string $checkoutSessionId = null): ?Payment
    {
        return DB::transaction(function () use ($invoice, $checkoutSessionId) {
            $user = null;
            $customerId = is_string($invoice->customer ?? null)
                ? $invoice->customer
                : ($invoice->customer->id ?? null);

            if ($customerId) {
                $user = User::where('stripe_id', $customerId)->first();
            }

            $subscriptionId = is_string($invoice->subscription ?? null)
                ? $invoice->subscription
                : ($invoice->subscription->id ?? null);

            $subscription = null;
            if ($subscriptionId) {
                $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();
                if ($subscription && ! $user) {
                    $user = $subscription->user;
                }
            }

            if (! $user) {
                Log::warning('Paid invoice missing local user', [
                    'invoice' => $invoice->id ?? null,
                    'customer' => $customerId,
                ]);

                return null;
            }

            if ($subscription && $subscriptionId) {
                try {
                    $stripeSub = $this->stripe->make()->subscriptions->retrieve($subscriptionId);
                    $this->subscriptions->syncFromStripeObject($subscription, $stripeSub);
                    $subscription->refresh();
                } catch (\Throwable $e) {
                    Log::warning('Failed refreshing subscription on paid invoice', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $amount = ((float) ($invoice->amount_paid ?? 0)) / 100;
            $paidAt = ! empty($invoice->status_transitions->paid_at)
                ? Carbon::createFromTimestamp($invoice->status_transitions->paid_at)
                : now();

            $paymentIntent = is_string($invoice->payment_intent ?? null)
                ? $invoice->payment_intent
                : ($invoice->payment_intent->id ?? null);

            $attrs = [
                'user_id' => $user->id,
                'plan_id' => $subscription?->plan_id,
                'subscription_id' => $subscription?->id,
                'stripe_payment_intent_id' => $paymentIntent,
                'amount' => $amount,
                'currency' => strtoupper($invoice->currency ?? 'usd'),
                'status' => 'paid',
                'billing_reason' => $invoice->billing_reason ?? 'subscription_create',
                'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                'paid_at' => $paidAt,
                'raw' => [
                    'id' => $invoice->id,
                    'number' => $invoice->number ?? null,
                    'billing_reason' => $invoice->billing_reason ?? null,
                ],
            ];

            if ($checkoutSessionId) {
                $attrs['stripe_checkout_session_id'] = $checkoutSessionId;
            }

            $payment = Payment::updateOrCreate(
                ['stripe_invoice_id' => $invoice->id],
                $attrs
            );

            Log::info('Payment recorded from Stripe invoice', [
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $payment->amount,
                'user_id' => $user->id,
                'source' => $checkoutSessionId ? 'checkout_session' : 'webhook_or_sync',
            ]);

            return $payment;
        });
    }

    /**
     * Sync all paid invoices for a local Stripe subscription (backfill / repair).
     *
     * @return int Number of payment rows upserted
     */
    public function syncPaidInvoicesForSubscription(Subscription $subscription): int
    {
        if (! $subscription->stripe_subscription_id) {
            return 0;
        }

        $count = 0;
        $startingAfter = null;

        do {
            $params = [
                'subscription' => $subscription->stripe_subscription_id,
                'status' => 'paid',
                'limit' => 100,
            ];
            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            $page = $this->stripe->make()->invoices->all($params);

            foreach ($page->data ?? [] as $invoice) {
                if ($this->recordPaidInvoice($invoice)) {
                    $count++;
                }
            }

            $startingAfter = (! empty($page->has_more) && ! empty($page->data))
                ? end($page->data)->id
                : null;
        } while ($startingAfter);

        return $count;
    }

    private function handleSubscriptionUpsert(object $stripeSub, array $fallbackMeta = []): void
    {
        DB::transaction(function () use ($stripeSub, $fallbackMeta) {
            $meta = $stripeSub->metadata ?? (object) [];
            $userId = (int) ($meta->user_id ?? $fallbackMeta['user_id'] ?? 0);
            $planId = (int) ($meta->plan_id ?? $fallbackMeta['plan_id'] ?? 0);
            $planSlug = $meta->plan_slug ?? ($fallbackMeta['plan_slug'] ?? null);
            $billingCycle = $meta->billing_cycle ?? ($fallbackMeta['billing_cycle'] ?? null);

            $user = $userId ? User::find($userId) : null;

            if (! $user && ! empty($stripeSub->customer)) {
                $customerId = is_string($stripeSub->customer)
                    ? $stripeSub->customer
                    : ($stripeSub->customer->id ?? null);
                if ($customerId) {
                    $user = User::where('stripe_id', $customerId)->first();
                }
            }

            if (! $user) {
                Log::warning('Stripe subscription webhook missing local user', [
                    'stripe_subscription_id' => $stripeSub->id,
                ]);

                return;
            }

            $customerId = is_string($stripeSub->customer ?? null)
                ? $stripeSub->customer
                : ($stripeSub->customer->id ?? null);

            if ($customerId && $user->stripe_id !== $customerId) {
                $user->forceFill(['stripe_id' => $customerId])->save();
            }

            $plan = $planId ? Plan::find($planId) : null;
            if (! $plan && $planSlug) {
                $plan = Plan::where('slug', $planSlug)->first();
            }

            $subscription = Subscription::where('stripe_subscription_id', $stripeSub->id)->first();

            if (! $subscription) {
                $subscription = Subscription::where('user_id', $user->id)
                    ->whereIn('stripe_status', ['active', 'trialing', 'past_due', 'incomplete'])
                    ->whereNull('stripe_subscription_id')
                    ->latest()
                    ->first();
            }

            $periodStart = isset($stripeSub->current_period_start)
                ? Carbon::createFromTimestamp($stripeSub->current_period_start)
                : now();
            $periodEnd = isset($stripeSub->current_period_end)
                ? Carbon::createFromTimestamp($stripeSub->current_period_end)
                : null;
            $trialEnd = ! empty($stripeSub->trial_end)
                ? Carbon::createFromTimestamp($stripeSub->trial_end)
                : null;
            $cancelAtPeriodEnd = (bool) ($stripeSub->cancel_at_period_end ?? false);

            $endsAt = null;
            if (! empty($stripeSub->ended_at)) {
                $endsAt = Carbon::createFromTimestamp($stripeSub->ended_at);
            } elseif ($cancelAtPeriodEnd && $periodEnd) {
                $endsAt = $periodEnd;
            } elseif (! empty($stripeSub->cancel_at)) {
                $endsAt = Carbon::createFromTimestamp($stripeSub->cancel_at);
            }

            $amount = $plan?->price ?? 0;
            $currency = strtoupper($plan?->currency ?? 'USD');

            if (! empty($stripeSub->items->data[0]->price->unit_amount)) {
                $amount = ((float) $stripeSub->items->data[0]->price->unit_amount) / 100;
                $currency = strtoupper($stripeSub->items->data[0]->price->currency ?? $currency);
            }

            $payload = [
                'user_id' => $user->id,
                'plan_id' => $plan?->id,
                'stripe_subscription_id' => $stripeSub->id,
                'stripe_status' => $stripeSub->status,
                'billing_cycle' => $billingCycle ?: ($plan?->billing_cycle ?? 'monthly'),
                'amount' => $amount,
                'currency' => $currency,
                'trial_ends_at' => $trialEnd,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'ends_at' => $endsAt,
                'source' => 'stripe',
            ];

            if ($subscription) {
                $subscription->update($payload);
            } else {
                Subscription::create($payload);
            }
        });
    }

    private function handleSubscriptionDeleted(object $stripeSub): void
    {
        $subscription = Subscription::where('stripe_subscription_id', $stripeSub->id)->first();

        if (! $subscription) {
            return;
        }

        $endsAt = ! empty($stripeSub->ended_at)
            ? Carbon::createFromTimestamp($stripeSub->ended_at)
            : now();

        $subscription->update([
            'stripe_status' => 'canceled',
            'cancel_at_period_end' => false,
            'ends_at' => $endsAt,
        ]);
    }

    private function handleInvoicePaymentFailed(object $invoice): void
    {
        $subscriptionId = is_string($invoice->subscription ?? null)
            ? $invoice->subscription
            : ($invoice->subscription->id ?? null);

        $subscription = null;
        if ($subscriptionId) {
            $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();
            if ($subscription) {
                $subscription->update(['stripe_status' => 'past_due']);
            }
        }

        $user = $subscription?->user;
        $customerId = is_string($invoice->customer ?? null)
            ? $invoice->customer
            : ($invoice->customer->id ?? null);

        if (! $user && $customerId) {
            $user = User::where('stripe_id', $customerId)->first();
        }

        if (! $user) {
            return;
        }

        Payment::updateOrCreate(
            ['stripe_invoice_id' => $invoice->id],
            [
                'user_id' => $user->id,
                'plan_id' => $subscription?->plan_id,
                'subscription_id' => $subscription?->id,
                'stripe_payment_intent_id' => is_string($invoice->payment_intent ?? null)
                    ? $invoice->payment_intent
                    : ($invoice->payment_intent->id ?? null),
                'amount' => ((float) ($invoice->amount_due ?? 0)) / 100,
                'currency' => strtoupper($invoice->currency ?? 'usd'),
                'status' => 'failed',
                'billing_reason' => $invoice->billing_reason ?? 'subscription_cycle',
                'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                'paid_at' => null,
                'raw' => [
                    'id' => $invoice->id,
                    'attempt_count' => $invoice->attempt_count ?? null,
                ],
            ]
        );
    }
}

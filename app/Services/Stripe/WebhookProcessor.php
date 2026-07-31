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
        match ($event->type) {
            'checkout.session.completed' => $this->syncCheckoutSession($event->data->object),
            'customer.subscription.created',
            'customer.subscription.updated' => $this->handleSubscriptionUpsert($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            'invoice.paid' => $this->handleInvoicePaid($event->data->object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
            default => Log::debug('Unhandled Stripe webhook event', ['type' => $event->type]),
        };
    }

    public function syncCheckoutSession(object $session): void
    {
        $userId = (int) ($session->metadata->user_id ?? $session->client_reference_id ?? 0);
        $user = $userId ? User::find($userId) : null;

        if ($user && ! empty($session->customer) && $user->stripe_id !== $session->customer) {
            $user->forceFill(['stripe_id' => $session->customer])->save();
        }

        if (! empty($session->subscription)) {
            try {
                $stripeSub = $this->stripe->make()->subscriptions->retrieve($session->subscription);
                $this->handleSubscriptionUpsert($stripeSub, [
                    'user_id' => $session->metadata->user_id ?? null,
                    'plan_id' => $session->metadata->plan_id ?? null,
                    'plan_slug' => $session->metadata->plan_slug ?? null,
                    'billing_cycle' => $session->metadata->billing_cycle ?? null,
                    'checkout_session_id' => $session->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to sync subscription after checkout.session.completed', [
                    'session_id' => $session->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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
                $user = User::where('stripe_id', $stripeSub->customer)->first();
            }

            if (! $user) {
                Log::warning('Stripe subscription webhook missing local user', [
                    'stripe_subscription_id' => $stripeSub->id,
                ]);

                return;
            }

            if (! empty($stripeSub->customer) && $user->stripe_id !== $stripeSub->customer) {
                $user->forceFill(['stripe_id' => $stripeSub->customer])->save();
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

    private function handleInvoicePaid(object $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $user = null;
            if (! empty($invoice->customer)) {
                $user = User::where('stripe_id', $invoice->customer)->first();
            }

            $subscription = null;
            if (! empty($invoice->subscription)) {
                $subscription = Subscription::where('stripe_subscription_id', $invoice->subscription)->first();
                if ($subscription && ! $user) {
                    $user = $subscription->user;
                }
            }

            if (! $user) {
                Log::warning('invoice.paid missing local user', ['invoice' => $invoice->id ?? null]);

                return;
            }

            if ($subscription) {
                try {
                    $stripeSub = $this->stripe->make()->subscriptions->retrieve($invoice->subscription);
                    $this->subscriptions->syncFromStripeObject($subscription, $stripeSub);
                    $subscription->refresh();
                } catch (\Throwable $e) {
                    Log::warning('Failed refreshing subscription on invoice.paid', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $amount = ((float) ($invoice->amount_paid ?? 0)) / 100;
            $paidAt = ! empty($invoice->status_transitions->paid_at)
                ? Carbon::createFromTimestamp($invoice->status_transitions->paid_at)
                : now();

            Payment::updateOrCreate(
                ['stripe_invoice_id' => $invoice->id],
                [
                    'user_id' => $user->id,
                    'plan_id' => $subscription?->plan_id,
                    'subscription_id' => $subscription?->id,
                    'stripe_payment_intent_id' => is_string($invoice->payment_intent ?? null)
                        ? $invoice->payment_intent
                        : ($invoice->payment_intent->id ?? null),
                    'amount' => $amount,
                    'currency' => strtoupper($invoice->currency ?? 'usd'),
                    'status' => 'paid',
                    'billing_reason' => $invoice->billing_reason ?? 'subscription_cycle',
                    'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                    'paid_at' => $paidAt,
                    'raw' => [
                        'id' => $invoice->id,
                        'number' => $invoice->number ?? null,
                        'billing_reason' => $invoice->billing_reason ?? null,
                    ],
                ]
            );
        });
    }

    private function handleInvoicePaymentFailed(object $invoice): void
    {
        $subscription = null;
        if (! empty($invoice->subscription)) {
            $subscription = Subscription::where('stripe_subscription_id', $invoice->subscription)->first();
            if ($subscription) {
                $subscription->update(['stripe_status' => 'past_due']);
            }
        }

        $user = $subscription?->user;
        if (! $user && ! empty($invoice->customer)) {
            $user = User::where('stripe_id', $invoice->customer)->first();
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

<?php

namespace App\Services\Stripe;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Payment;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Services\Billing\AccessStateResolver;
use App\Services\Entitlements\RetainedFeatures;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies Stripe subscriptions and invoices to a company (07 §6.2). Callers hold the company
 * lock (StripeEventProcessor) or run inside one transaction here.
 */
class SubscriptionSync
{
    private const STATUSES = ['trialing', 'active', 'past_due', 'unpaid', 'canceled', 'incomplete', 'incomplete_expired'];

    public function __construct(
        private readonly StripeClientFactory $stripe,
    ) {}

    /**
     * Upserts the local row for a Stripe subscription. Returns null when an incomplete
     * subscription was not stored because the company has an open row already.
     *
     * @param  array<string, mixed>  $fallbackMeta  checkout session metadata when the subscription has none
     */
    public function upsert(Company $company, object $stripeSub, ?CarbonInterface $eventAt = null, array $fallbackMeta = []): ?Subscription
    {
        return DB::transaction(function () use ($company, $stripeSub, $eventAt, $fallbackMeta) {
            $existing = Subscription::query()->where('stripe_subscription_id', $stripeSub->id)->lockForUpdate()->first();

            if ($existing && $existing->company_id !== $company->id) {
                throw new StripeEventRejected("Stripe subscription {$stripeSub->id} belongs to another company.");
            }

            $attributes = $this->attributes($company, $stripeSub, $existing, $fallbackMeta);

            if ($eventAt && (! $existing?->last_stripe_event_at || $eventAt->gt($existing->last_stripe_event_at))) {
                $attributes['last_stripe_event_at'] = $eventAt;
            }

            // A canceled Stripe subscription is terminal, and a row another subscription replaced
            // stays history: a same-second or late update must not reopen either (two open rows).
            if ($existing?->ended_at && $attributes['ended_at'] === null
                && (in_array($existing->status, ['canceled', 'incomplete_expired'], true)
                    || $company->subscriptions()->open()->whereKeyNot($existing->id)->exists())) {
                return $existing;
            }

            if ($existing) {
                $fromPriceId = $existing->plan_price_id;
                $existing->fill($attributes);
                $changes = $existing->getDirty();
                $existing->save();
                RetainedFeatures::priceChanged($company, $fromPriceId, $existing->plan_price_id);
                $this->changed($company, $existing, $changes);

                return $existing;
            }

            $open = $company->subscriptions()->open()->lockForUpdate()->first();
            $fromPriceId = RetainedFeatures::lastPriceId($company);

            if ($open && $attributes['ended_at'] === null) {
                // An unpaid checkout never displaces a row the company still has (07 §4.1).
                if (! in_array($attributes['status'], ['active', 'trialing', 'past_due'], true)) {
                    Log::info('Incomplete Stripe subscription not stored; the company has an open row', [
                        'company_id' => $company->id,
                        'stripe_subscription_id' => $stripeSub->id,
                    ]);

                    return null;
                }

                if ($open->stripe_subscription_id && $open->isAccessible()) {
                    Log::warning('A second accessible Stripe subscription replaced the open one', [
                        'company_id' => $company->id,
                        'replaced' => $open->stripe_subscription_id,
                        'new' => $stripeSub->id,
                    ]);
                }

                // Trial conversion or a new subscription after a lapse: the old row becomes history.
                $open->update(['ended_at' => now()] + ($open->stripe_subscription_id ? [] : ['status' => 'canceled']));
            }

            $subscription = Subscription::query()->create($attributes + [
                'actor_user_id' => $this->actorId($company, $stripeSub, $fallbackMeta),
            ]);
            RetainedFeatures::priceChanged($company, $fromPriceId, $subscription->plan_price_id);
            $this->changed($company, $subscription, $subscription->getAttributes());

            return $subscription;
        });
    }

    /**
     * invoice.payment_failed for a renewal: the row goes past_due and grace starts.
     */
    public function markPastDue(Subscription $subscription, CarbonInterface $eventAt): void
    {
        if (! in_array($subscription->status, ['active', 'past_due'], true)) {
            return;
        }

        $subscription->fill(['status' => 'past_due', 'last_stripe_event_at' => $eventAt]);
        $changes = $subscription->getDirty();
        $subscription->save();
        $this->changed($subscription->company, $subscription, $changes);
    }

    /**
     * Pulls the live subscription for a checkout session and records its first invoice.
     */
    public function syncCheckoutSession(Company $company, object $session): ?Subscription
    {
        $subscriptionId = $this->idOf($session->subscription ?? null);
        $subscription = null;

        if ($subscriptionId) {
            // Live state is newer than every event created before now: stamp the ordering guard.
            $stripeSub = $this->stripe->make()->subscriptions->retrieve($subscriptionId);
            $subscription = $this->upsert($company, $stripeSub, now(), [
                'plan_price_id' => $session->metadata->plan_price_id ?? null,
                'actor_user_id' => $session->metadata->actor_user_id ?? null,
            ]);
        }

        $invoiceId = $this->idOf($session->invoice ?? null);
        if ($invoiceId && in_array($session->payment_status ?? null, ['paid', 'no_payment_required'], true)) {
            $this->recordInvoice($company, $this->stripe->make()->invoices->retrieve($invoiceId), 'paid', $session->id ?? null);
        }

        return $subscription;
    }

    /**
     * Idempotent invoice → payments row, keyed by invoice id. Financial history: never deleted.
     */
    public function recordInvoice(Company $company, object $invoice, string $status, ?string $checkoutSessionId = null): Payment
    {
        $subscriptionId = $this->invoiceSubscriptionId($invoice);
        $subscription = $subscriptionId
            ? $company->subscriptions()->where('stripe_subscription_id', $subscriptionId)->with('planPrice')->first()
            : null;

        $paid = $status === 'paid';
        $recorded = Payment::query()->where('stripe_invoice_id', $invoice->id)->first();

        // A late or redelivered failure never overwrites a payment already recorded as paid.
        if ($recorded?->status === 'paid' && ! $paid) {
            return $recorded;
        }

        $attributes = [
            'company_id' => $company->id,
            'plan_id' => $subscription?->planPrice?->plan_id,
            'subscription_id' => $subscription?->id,
            'stripe_payment_intent_id' => $this->idOf($invoice->payment_intent ?? null),
            'amount' => ((float) ($paid ? ($invoice->amount_paid ?? 0) : ($invoice->amount_due ?? 0))) / 100,
            'currency' => strtoupper($invoice->currency ?? 'usd'),
            'status' => $status,
            'billing_reason' => $invoice->billing_reason ?? null,
            'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
            'paid_at' => $paid ? $this->time($invoice->status_transitions->paid_at ?? null) ?? now() : null,
            'raw' => [
                'id' => $invoice->id,
                'number' => $invoice->number ?? null,
                'attempt_count' => $invoice->attempt_count ?? null,
            ],
        ];

        if ($checkoutSessionId) {
            $attributes['stripe_checkout_session_id'] = $checkoutSessionId;
        }

        return Payment::query()->updateOrCreate(['stripe_invoice_id' => $invoice->id], $attributes);
    }

    /**
     * The subscription id on an invoice, in the old and the 2025 API shapes.
     */
    public function invoiceSubscriptionId(object $invoice): ?string
    {
        return $this->idOf($invoice->subscription ?? null)
            ?? $this->idOf(data_get($invoice, 'parent.subscription_details.subscription'));
    }

    public function idOf(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return is_object($value) && isset($value->id) ? (string) $value->id : null;
    }

    /**
     * @param  array<string, mixed>  $fallbackMeta
     * @return array<string, mixed>
     */
    private function attributes(Company $company, object $stripeSub, ?Subscription $existing, array $fallbackMeta): array
    {
        $item = $stripeSub->items->data[0] ?? null;
        $stripePriceId = $this->idOf($item->price ?? null);
        $price = $stripePriceId ? PlanPrice::query()->where('stripe_price_id', $stripePriceId)->first() : null;

        // Unknown price (07 §3): keep the last known price, store the raw values, flag for review.
        $planPriceId = $price->id
            ?? $existing->plan_price_id
            ?? $this->knownPriceId($stripeSub->metadata->plan_price_id ?? $fallbackMeta['plan_price_id'] ?? null);

        if (! $planPriceId) {
            throw new StripeEventRejected("Stripe price {$stripePriceId} is unknown and the subscription has no plan_price_id.");
        }

        $status = in_array($stripeSub->status, self::STATUSES, true) ? $stripeSub->status : 'unpaid';
        $needsReview = $price === null || $status !== $stripeSub->status;

        if ($needsReview) {
            Log::critical('Stripe subscription needs review: unknown price or status', [
                'company_id' => $company->id,
                'stripe_subscription_id' => $stripeSub->id,
                'stripe_price_id' => $stripePriceId,
                'status' => $stripeSub->status,
            ]);
        }

        $periodStart = $this->time($stripeSub->current_period_start ?? $item->current_period_start ?? null);
        $periodEnd = $this->time($stripeSub->current_period_end ?? $item->current_period_end ?? null);
        $cancelAtPeriodEnd = (bool) ($stripeSub->cancel_at_period_end ?? false);
        $endedAt = $this->time($stripeSub->ended_at ?? null);
        $endsAt = $endedAt
            ?? ($cancelAtPeriodEnd ? $periodEnd : null)
            ?? $this->time($stripeSub->cancel_at ?? null);

        return [
            'company_id' => $company->id,
            'plan_price_id' => $planPriceId,
            'stripe_subscription_id' => $stripeSub->id,
            'stripe_price_id' => $stripePriceId,
            'interval' => in_array($item->price->recurring->interval ?? null, ['month', 'year'], true)
                ? $item->price->recurring->interval
                : ($price->interval ?? $existing->interval ?? 'month'),
            'amount' => isset($item->price->unit_amount) ? ((float) $item->price->unit_amount) / 100 : ($price->amount ?? 0),
            'currency' => strtoupper($item->price->currency ?? $price->currency ?? 'usd'),
            'status' => $status,
            'source' => 'stripe',
            'trial_ends_at' => $this->time($stripeSub->trial_end ?? null),
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'ends_at' => $endsAt,
            // A canceled Stripe subscription is over: Stripe cancels at the period end or now.
            'ended_at' => in_array($status, ['canceled', 'incomplete_expired'], true) ? ($endedAt ?? now()) : null,
            'needs_review' => $needsReview,
        ];
    }

    private function knownPriceId(mixed $id): ?int
    {
        return is_numeric($id) ? PlanPrice::query()->whereKey((int) $id)->value('id') : null;
    }

    /**
     * @param  array<string, mixed>  $fallbackMeta
     */
    private function actorId(Company $company, object $stripeSub, array $fallbackMeta): ?int
    {
        $id = $stripeSub->metadata->actor_user_id ?? $fallbackMeta['actor_user_id'] ?? null;

        return is_numeric($id) ? $company->users()->whereKey((int) $id)->value('id') : null;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function changed(Company $company, Subscription $subscription, array $changes): void
    {
        unset($changes['updated_at'], $changes['created_at'], $changes['last_stripe_event_at']);

        if ($changes === []) {
            return;
        }

        $company->increment('entitlements_version');
        app(AccessStateResolver::class)->forget($company->id);
        AuditLog::recordStripe($company->id, 'subscription.synced', $subscription, array_map(
            fn ($value) => $value instanceof CarbonInterface ? $value->toIso8601String() : $value,
            $changes,
        ));
    }

    private function time(mixed $timestamp): ?Carbon
    {
        return is_numeric($timestamp) && (int) $timestamp > 0 ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }
}

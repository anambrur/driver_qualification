<?php

namespace App\Services\Stripe;

use App\Models\Company;
use App\Models\PlanPrice;
use App\Models\Subscription;
use InvalidArgumentException;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Exception\ApiErrorException;

/**
 * Cancel, resume and price moves. A Stripe row is changed in Stripe and synced back; a local
 * row (trial, admin grant) is changed here and never reaches Stripe.
 */
class SubscriptionService
{
    public function __construct(
        private readonly StripeClientFactory $stripe,
        private readonly CheckoutService $checkout,
        private readonly SubscriptionSync $sync,
    ) {}

    public function cancelAtPeriodEnd(Subscription $subscription): Subscription
    {
        if ($subscription->stripe_subscription_id) {
            return $this->syncStripe($subscription, $this->stripe->make()->subscriptions->update(
                $subscription->stripe_subscription_id,
                ['cancel_at_period_end' => true]
            ));
        }

        $subscription->update([
            'cancel_at_period_end' => true,
            'ends_at' => $subscription->accessEndsAt() ?? now(),
        ]);

        return $subscription->fresh();
    }

    public function cancelNow(Subscription $subscription): Subscription
    {
        if ($subscription->stripe_subscription_id) {
            return $this->syncStripe($subscription, $this->stripe->make()->subscriptions->cancel(
                $subscription->stripe_subscription_id
            ));
        }

        $subscription->update([
            'status' => 'canceled',
            'cancel_at_period_end' => false,
            'ends_at' => now(),
            'ended_at' => now(),
        ]);

        return $subscription->fresh();
    }

    /**
     * A local row keeps its end date: a trial or grant with no end would grant access forever.
     */
    public function resume(Subscription $subscription): Subscription
    {
        if (! $subscription->canResume()) {
            throw new InvalidArgumentException('This subscription cannot be resumed.');
        }

        if (! $subscription->stripe_subscription_id) {
            $subscription->update(['cancel_at_period_end' => $subscription->source !== 'trial']);

            return $subscription->fresh();
        }

        return $this->syncStripe($subscription, $this->stripe->make()->subscriptions->update(
            $subscription->stripe_subscription_id,
            ['cancel_at_period_end' => false]
        ));
    }

    /**
     * Platform "move to price" (07 §7): immediate, prorated. The webhook confirms plan_price_id.
     */
    public function moveToPrice(Subscription $subscription, PlanPrice $price): Subscription
    {
        if (! $subscription->stripe_subscription_id || ! $price->stripe_price_id || $price->archived_at !== null) {
            throw new InvalidArgumentException('Only a Stripe subscription can move to a current Stripe price.');
        }

        $client = $this->stripe->make();
        $current = $client->subscriptions->retrieve($subscription->stripe_subscription_id);

        return $this->syncStripe($subscription, $client->subscriptions->update($subscription->stripe_subscription_id, [
            'items' => [['id' => $current->items->data[0]->id, 'price' => $price->stripe_price_id]],
            'proration_behavior' => 'create_prorations',
        ]));
    }

    /**
     * @throws ApiErrorException
     */
    public function createBillingPortalSession(Company $company, string $returnUrl): PortalSession
    {
        return $this->stripe->make()->billingPortal->sessions->create([
            'customer' => $this->checkout->customerFor($company)->id,
            'return_url' => $returnUrl,
        ]);
    }

    private function syncStripe(Subscription $subscription, object $stripeSub): Subscription
    {
        // The API response is live state: stamp the ordering guard so older queued events are stale.
        return $this->sync->upsert($subscription->company, $stripeSub, now()) ?? $subscription->fresh();
    }
}

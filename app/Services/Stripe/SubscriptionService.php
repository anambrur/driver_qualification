<?php

namespace App\Services\Stripe;

use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use InvalidArgumentException;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Exception\ApiErrorException;

class SubscriptionService
{
    public function __construct(
        private readonly StripeClientFactory $stripe,
        private readonly CheckoutService $checkout
    ) {}

    public function cancelAtPeriodEnd(Subscription $subscription): Subscription
    {
        if ($subscription->stripe_subscription_id) {
            $stripeSub = $this->stripe->make()->subscriptions->update(
                $subscription->stripe_subscription_id,
                ['cancel_at_period_end' => true]
            );

            return $this->syncFromStripeObject($subscription, $stripeSub);
        }

        $endsAt = $subscription->accessEndsAt() ?? now();

        $subscription->update([
            'cancel_at_period_end' => true,
            'stripe_status' => $subscription->stripe_status === 'trialing' ? 'trialing' : 'canceled',
            'ends_at' => $endsAt,
        ]);

        return $subscription->fresh();
    }

    public function cancelNow(Subscription $subscription): Subscription
    {
        if ($subscription->stripe_subscription_id) {
            $stripeSub = $this->stripe->make()->subscriptions->cancel(
                $subscription->stripe_subscription_id
            );

            return $this->syncFromStripeObject($subscription, $stripeSub);
        }

        $subscription->update([
            'stripe_status' => 'canceled',
            'cancel_at_period_end' => false,
            'ends_at' => now(),
        ]);

        return $subscription->fresh();
    }

    public function resume(Subscription $subscription): Subscription
    {
        if (! $subscription->stripe_subscription_id) {
            if (! $subscription->onGracePeriod()) {
                throw new InvalidArgumentException('This subscription cannot be resumed.');
            }

            $subscription->update([
                'cancel_at_period_end' => false,
                'stripe_status' => $subscription->billing_cycle === 'trial' ? 'trialing' : 'active',
                'ends_at' => null,
            ]);

            return $subscription->fresh();
        }

        $stripeSub = $this->stripe->make()->subscriptions->update(
            $subscription->stripe_subscription_id,
            ['cancel_at_period_end' => false]
        );

        return $this->syncFromStripeObject($subscription, $stripeSub);
    }

    public function createBillingPortalSession(User $user, string $returnUrl): PortalSession
    {
        if (! $user->stripe_id) {
            $this->checkout->createOrGetCustomer($user);
            $user->refresh();
        }

        if (! $user->stripe_id) {
            throw new InvalidArgumentException('Unable to create a Stripe customer for billing portal.');
        }

        return $this->stripe->make()->billingPortal->sessions->create([
            'customer' => $user->stripe_id,
            'return_url' => $returnUrl,
        ]);
    }

    /**
     * @param  object  $stripeSub
     */
    public function syncFromStripeObject(Subscription $subscription, $stripeSub): Subscription
    {
        $periodStart = isset($stripeSub->current_period_start)
            ? Carbon::createFromTimestamp($stripeSub->current_period_start)
            : $subscription->current_period_start;
        $periodEnd = isset($stripeSub->current_period_end)
            ? Carbon::createFromTimestamp($stripeSub->current_period_end)
            : $subscription->current_period_end;
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

        $subscription->update([
            'stripe_subscription_id' => $stripeSub->id,
            'stripe_status' => $stripeSub->status,
            'trial_ends_at' => $trialEnd,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'ends_at' => $endsAt,
            'source' => 'stripe',
        ]);

        return $subscription->fresh();
    }

    /**
     * @throws ApiErrorException
     */
    public function retrieveStripeSubscription(string $stripeSubscriptionId)
    {
        return $this->stripe->make()->subscriptions->retrieve($stripeSubscriptionId);
    }
}

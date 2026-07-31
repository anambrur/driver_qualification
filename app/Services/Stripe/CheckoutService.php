<?php

namespace App\Services\Stripe;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;

class CheckoutService
{
    public function __construct(
        private readonly StripeClientFactory $stripe
    ) {}

    public function createOrGetCustomer(User $user): Customer
    {
        $client = $this->stripe->make();

        if ($user->stripe_id) {
            try {
                return $client->customers->retrieve($user->stripe_id);
            } catch (ApiErrorException $e) {
                Log::warning('Stored Stripe customer missing; creating a new one.', [
                    'user_id' => $user->id,
                    'stripe_id' => $user->stripe_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $customer = $client->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => (string) $user->id,
            ],
        ]);

        $user->forceFill(['stripe_id' => $customer->id])->save();

        return $customer;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws ApiErrorException
     */
    public function createSubscriptionCheckout(User $user, Plan $plan): Session
    {
        if (! $plan->is_active) {
            throw new \InvalidArgumentException('This plan is not available.');
        }

        if ($plan->isTrial() || $plan->isFree()) {
            throw new \InvalidArgumentException('Trial and free plans cannot use Stripe Checkout.');
        }

        if (! in_array($plan->billing_cycle, ['monthly', 'yearly'], true)) {
            throw new \InvalidArgumentException('Only monthly and yearly plans can be checked out with Stripe.');
        }

        $interval = $plan->stripeInterval();
        if (! $interval) {
            throw new \InvalidArgumentException('Invalid billing cycle for Stripe Checkout.');
        }

        $customer = $this->createOrGetCustomer($user);

        $subscriptionData = [
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
                'billing_cycle' => $plan->billing_cycle,
            ],
        ];

        if ((int) $plan->trial_days > 0) {
            $subscriptionData['trial_period_days'] = (int) $plan->trial_days;
        }

        return $this->stripe->make()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customer->id,
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($plan->currency ?: 'usd'),
                    'unit_amount' => $plan->amountInCents(),
                    'recurring' => [
                        'interval' => $interval,
                    ],
                    'product_data' => [
                        'name' => $plan->name,
                        'description' => $plan->description ?: $plan->name,
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('pricing.plans'),
            'client_reference_id' => (string) $user->id,
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
                'billing_cycle' => $plan->billing_cycle,
            ],
            'subscription_data' => $subscriptionData,
            'allow_promotion_codes' => true,
        ]);
    }
}

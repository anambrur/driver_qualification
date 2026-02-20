<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\User;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Webhook;
use Illuminate\Support\Facades\Log;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Get or create a Stripe customer for the user.
     */
    public function getOrCreateCustomer(User $user): Customer
    {
        if ($user->stripe_customer_id) {
            return Customer::retrieve($user->stripe_customer_id);
        }

        $customer = Customer::create([
            'email' => $user->email,
            'name'  => $user->name,
            'metadata' => ['user_id' => $user->id],
        ]);

        $user->update(['stripe_customer_id' => $customer->id]);

        return $customer;
    }

    /**
     * Create a PaymentIntent for one-time plan purchase.
     */
    public function createPaymentIntent(User $user, Plan $plan): PaymentIntent
    {
        $customer = $this->getOrCreateCustomer($user);

        return PaymentIntent::create([
            'amount'      => (int) ($plan->price * 100), // cents
            'currency'    => strtolower($plan->currency),
            'customer'    => $customer->id,
            'description' => "Subscription: {$plan->name}",
            'metadata'    => [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'plan_slug' => $plan->slug,
            ],
            'automatic_payment_methods' => ['enabled' => true],
        ]);
    }

    /**
     * Construct and verify a Stripe webhook event.
     */
    public function constructWebhookEvent(string $payload, string $signature): \Stripe\Event
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            config('services.stripe.webhook_secret')
        );
    }

    /**
     * Retrieve a PaymentIntent by ID.
     */
    public function retrievePaymentIntent(string $intentId): PaymentIntent
    {
        return PaymentIntent::retrieve($intentId);
    }
}

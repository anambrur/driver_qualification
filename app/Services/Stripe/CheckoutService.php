<?php

namespace App\Services\Stripe;

use App\Models\Company;
use App\Models\PlanPrice;
use App\Models\User;
use App\Services\Billing\AccessStateResolver;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;

class CheckoutService
{
    public function __construct(
        private readonly StripeClientFactory $stripe
    ) {}

    /**
     * The company's Stripe customer (07 §2), created on first use and tagged with the app host
     * because the Stripe account is shared (PO-18).
     */
    public function customerFor(Company $company): Customer
    {
        $client = $this->stripe->make();
        $meta = $this->appMetadata() + ['company_id' => (string) $company->id];

        if ($company->stripe_customer_id) {
            try {
                return $client->customers->update($company->stripe_customer_id, ['metadata' => $meta]);
            } catch (ApiErrorException $e) {
                Log::warning('Stored Stripe customer missing; creating a new one.', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $customer = $client->customers->create([
            'email' => $company->billing_email ?: $company->owner?->email,
            'name' => $company->company_name,
            'description' => $this->appLabel().' company #'.$company->id,
            'metadata' => $meta,
        ]);

        $company->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer;
    }

    /**
     * @throws InvalidArgumentException when the price cannot be bought or the company already pays
     * @throws ApiErrorException
     */
    public function createSubscriptionCheckout(Company $company, User $actor, PlanPrice $price): Session
    {
        $price->loadMissing('plan');

        if ($price->archived_at !== null || ! $price->plan?->is_active || ! $price->plan->is_public || ! $price->stripe_price_id) {
            throw new InvalidArgumentException('This plan is not available.');
        }

        // A trialing company may convert; a paying one changes plan in the billing portal (07 §4.1).
        $open = AccessStateResolver::accessibleSubscription($company);
        if ($open && $open->source !== 'trial') {
            throw new InvalidArgumentException('Your company already has an active subscription. Manage it from Billing.');
        }

        $meta = $this->appMetadata() + [
            'company_id' => (string) $company->id,
            'actor_user_id' => (string) $actor->id,
            'plan_price_id' => (string) $price->id,
        ];

        return $this->stripe->make()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $this->customerFor($company)->id,
            'line_items' => [['price' => $price->stripe_price_id, 'quantity' => 1]],
            'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('pricing.plans'),
            'client_reference_id' => (string) $company->id,
            'metadata' => $meta,
            'subscription_data' => [
                'description' => $this->labeledDescription($price),
                'metadata' => $meta,
            ],
            'allow_promotion_codes' => true,
        ]);
    }

    public function appLabel(): string
    {
        return (string) (config('services.stripe.app_label') ?: config('app.name') ?: 'DriverFilesHub');
    }

    public function appHost(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host ?: 'driverfileshub.com';
    }

    /**
     * @return array<string, string>
     */
    public function appMetadata(): array
    {
        return [
            'application' => $this->appLabel(),
            'app_url' => (string) config('app.url'),
            'app_host' => $this->appHost(),
        ];
    }

    private function labeledDescription(PlanPrice $price): string
    {
        return sprintf('%s %s (%sly) · %s', $this->appLabel(), $price->plan->name, $price->interval, $this->appHost());
    }
}

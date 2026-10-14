<?php

namespace Tests\Feature\Modules\Billing;

use App\Models\Company;
use App\Models\PlanPrice;
use App\Models\SiteSetting;
use Database\Factories\PlanFactory;
use Database\Factories\PlanPriceFactory;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use Mockery\MockInterface;
use Stripe\Checkout\Session;
use Stripe\Invoice;
use Stripe\Service\Checkout\SessionService;
use Stripe\Service\InvoiceService;
use Stripe\Service\SubscriptionService;
use Stripe\Subscription as StripeSubscription;
use Tests\Support\StripeFake;

/**
 * Stripe object fixtures for company billing (07). Everything is built locally; no request
 * ever leaves the test process.
 */
final class Billing
{
    /**
     * A current monthly price with a Stripe Price id, on a fresh public plan.
     */
    public static function price(array $attributes = []): PlanPrice
    {
        return PlanPriceFactory::new()->create($attributes + [
            'stripe_price_id' => 'price_test_'.Str::lower(Str::random(10)),
        ]);
    }

    /**
     * The trial plan in site_settings (Q5: the top tier), with a current monthly price.
     */
    public static function trialPlan(int $days = 14): PlanPrice
    {
        $price = PlanPriceFactory::new()->for(PlanFactory::new()->state(['name' => 'Enterprise Trial']))->create();
        $settings = SiteSetting::getSettings();
        $settings->update(['trial_plan_id' => $price->plan_id, 'trial_days' => $days]);

        return $price;
    }

    public static function customerId(Company $company): string
    {
        return 'cus_test_'.$company->id;
    }

    public static function stripeSubscription(Company $company, PlanPrice $price, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'sub_test_'.$company->id,
            'object' => 'subscription',
            'customer' => self::customerId($company),
            'status' => 'active',
            'current_period_start' => now()->subDay()->timestamp,
            'current_period_end' => now()->addDays(29)->timestamp,
            'cancel_at_period_end' => false,
            'metadata' => [
                'company_id' => (string) $company->id,
                'plan_price_id' => (string) $price->id,
            ],
            'items' => [
                'object' => 'list',
                'data' => [[
                    'id' => 'si_test_'.$company->id,
                    'object' => 'subscription_item',
                    'price' => [
                        'id' => $price->stripe_price_id,
                        'object' => 'price',
                        'unit_amount' => 4900,
                        'currency' => 'usd',
                        'recurring' => ['interval' => 'month'],
                    ],
                ]],
            ],
        ], $overrides);
    }

    public static function checkoutSession(Company $company, PlanPrice $price, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'cs_test_'.$company->id,
            'object' => 'checkout.session',
            'customer' => self::customerId($company),
            'subscription' => 'sub_test_'.$company->id,
            'invoice' => 'in_test_'.$company->id,
            'status' => 'complete',
            'payment_status' => 'paid',
            'client_reference_id' => (string) $company->id,
            'metadata' => [
                'company_id' => (string) $company->id,
                'plan_price_id' => (string) $price->id,
                'actor_user_id' => (string) $company->owner_user_id,
            ],
        ], $overrides);
    }

    public static function invoice(Company $company, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'in_test_'.$company->id,
            'object' => 'invoice',
            'customer' => self::customerId($company),
            'subscription' => 'sub_test_'.$company->id,
            'amount_paid' => 4900,
            'amount_due' => 4900,
            'currency' => 'usd',
            'billing_reason' => 'subscription_create',
            'status_transitions' => ['paid_at' => now()->timestamp],
        ], $overrides);
    }

    /**
     * Bind a fake Stripe client whose subscriptions/invoices/checkout sessions answer with
     * the given objects. Returns the client mock so tests can add expectations.
     */
    public static function fakeStripe(?array $subscription = null, ?array $invoice = null, ?array $session = null): MockInterface
    {
        $client = StripeFake::bindClient();

        $subscriptions = Mockery::mock(SubscriptionService::class);
        if ($subscription !== null) {
            $subscriptions->shouldReceive('retrieve')->andReturn(StripeSubscription::constructFrom($subscription));
        }

        $invoices = Mockery::mock(InvoiceService::class);
        if ($invoice !== null) {
            $invoices->shouldReceive('retrieve')->andReturn(Invoice::constructFrom($invoice));
        }

        $sessions = Mockery::mock(SessionService::class);
        if ($session !== null) {
            $sessions->shouldReceive('retrieve')->andReturn(Session::constructFrom($session));
        }

        $client->subscriptions = $subscriptions;
        $client->invoices = $invoices;
        $client->checkout = (object) ['sessions' => $sessions];

        return $client;
    }

    /**
     * Posts a signed event; $created sets the event time for the ordering guard.
     */
    public static function postWebhook(TestCase $test, string $type, array $object, ?string $eventId = null, ?int $created = null): TestResponse
    {
        $payload = StripeFake::event($type, $object, $eventId, $created);

        return $test->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => StripeFake::signature($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }
}

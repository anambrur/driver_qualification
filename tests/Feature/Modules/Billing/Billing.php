<?php

namespace Tests\Feature\Modules\Billing;

use App\Models\Plan;
use App\Models\User;
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
 * Stripe object fixtures for the billing module. Everything is built locally; no request
 * ever leaves the test process.
 */
final class Billing
{
    public static function stripeSubscription(User $user, Plan $plan, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'sub_test_billing',
            'object' => 'subscription',
            'customer' => 'cus_test_billing',
            'status' => 'active',
            'current_period_start' => now()->subDay()->timestamp,
            'current_period_end' => now()->addDays(29)->timestamp,
            'cancel_at_period_end' => false,
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
                'billing_cycle' => $plan->billing_cycle,
            ],
            'items' => [
                'object' => 'list',
                'data' => [['object' => 'subscription_item', 'price' => ['unit_amount' => 4900, 'currency' => 'usd']]],
            ],
        ], $overrides);
    }

    public static function checkoutSession(User $user, Plan $plan, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'cs_test_billing',
            'object' => 'checkout.session',
            'customer' => 'cus_test_billing',
            'subscription' => 'sub_test_billing',
            'invoice' => 'in_test_billing',
            'status' => 'complete',
            'payment_status' => 'paid',
            'client_reference_id' => (string) $user->id,
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_id' => (string) $plan->id,
                'plan_slug' => $plan->slug,
                'billing_cycle' => $plan->billing_cycle,
            ],
        ], $overrides);
    }

    public static function invoice(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'in_test_billing',
            'object' => 'invoice',
            'customer' => 'cus_test_billing',
            'subscription' => 'sub_test_billing',
            'amount_paid' => 4900,
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

    public static function postWebhook(\Illuminate\Foundation\Testing\TestCase $test, string $type, array $object, ?string $eventId = null): \Illuminate\Testing\TestResponse
    {
        $payload = StripeFake::event($type, $object, $eventId);

        return $test->call('POST', route('stripe.webhook'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => StripeFake::signature($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }
}

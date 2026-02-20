<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class SubscriptionController extends Controller
{
    private StripeClient $stripe;

    public function __construct(private SubscriptionService $subscriptionService)
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    // ─── Plans Page ───────────────────────────────────────────────────────────

    public function plans(): View
    {
        $plans               = Plan::active()->ordered()->get();
        $user                = Auth::user();
        $currentSubscription = $user->currentSubscription();

        return view('subscription.plans', compact('plans', 'currentSubscription'));
    }

    // ─── Checkout Page ────────────────────────────────────────────────────────

    public function checkout(Plan $plan): View|RedirectResponse
    {
        if (!$plan->is_active) {
            return redirect()->route('subscription.plans')->with('error', 'This plan is not available.');
        }

        $user = Auth::user();

        if ($user->subscribedTo($plan->slug)) {
            return redirect()->route('subscription.my')->with('info', 'You are already on this plan.');
        }

        // Check if Stripe is configured
        $stripeEnabled = !empty(config('services.stripe.key'));

        return view('subscription.checkout', compact('plan', 'stripeEnabled'));
    }

    // ─── Stripe Checkout Session ──────────────────────────────────────────────

    /**
     * Create a Stripe Checkout Session and redirect user to Stripe.
     */
    public function createStripeCheckoutSession(Request $request, Plan $plan): RedirectResponse
    {
        $user = Auth::user();

        try {
            // Create or retrieve Stripe customer
            $customerId = $this->getOrCreateStripeCustomer($user);

            // Build line item
            $lineItem = [
                'price_data' => [
                    'currency'     => strtolower($plan->currency),
                    'unit_amount'  => (int) ($plan->price * 100), // cents
                    'product_data' => [
                        'name'        => $plan->name . ' Plan',
                        'description' => $plan->description,
                    ],
                ],
                'quantity' => 1,
            ];

            // For recurring plans, use subscription mode
            $mode = in_array($plan->billing_cycle, ['monthly', 'yearly']) ? 'subscription' : 'payment';

            if ($mode === 'subscription') {
                // Create a price object for recurring billing
                $interval = $plan->billing_cycle === 'yearly' ? 'year' : 'month';
                $lineItem  = [
                    'price_data' => [
                        'currency'        => strtolower($plan->currency),
                        'unit_amount'     => (int) ($plan->price * 100),
                        'recurring'       => ['interval' => $interval],
                        'product_data'    => [
                            'name'        => $plan->name . ' Plan',
                            'description' => $plan->description,
                        ],
                    ],
                    'quantity' => 1,
                ];
            }

            $session = $this->stripe->checkout->sessions->create([
                'customer'             => $customerId,
                'payment_method_types' => ['card'],
                'line_items'           => [$lineItem],
                'mode'                 => $mode,
                'success_url'          => route('subscription.payment.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'           => route('subscription.payment.cancel'),
                'metadata'             => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                ],
                'client_reference_id'  => $user->id,
            ]);

            // Store session ID in session for verification on success
            session(['stripe_checkout_plan_id' => $plan->id]);

            return redirect($session->url);

        } catch (\Exception $e) {
            Log::error('Stripe checkout session creation failed', [
                'user_id'  => $user->id,
                'plan_id'  => $plan->id,
                'error'    => $e->getMessage(),
            ]);

            return back()->with('error', 'Payment initialization failed. Please try again.');
        }
    }

    // ─── Payment Success (Stripe redirect back) ───────────────────────────────

    public function paymentSuccess(Request $request): View|RedirectResponse
    {
        $sessionId = $request->get('session_id');

        if (!$sessionId) {
            return redirect()->route('subscription.plans')->with('error', 'Invalid payment session.');
        }

        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId, [
                'expand' => ['payment_intent', 'subscription'],
            ]);

            if ($session->payment_status !== 'paid' && $session->status !== 'complete') {
                return redirect()->route('subscription.plans')
                    ->with('error', 'Payment was not completed. Please try again.');
            }

            $user   = Auth::user();
            $planId = $session->metadata->plan_id ?? session('stripe_checkout_plan_id');
            $plan   = Plan::findOrFail($planId);

            // Check if already processed (idempotency)
            $existingPayment = Payment::where('transaction_id', $session->id)->first();

            if (!$existingPayment) {
                $transactionId = $session->payment_intent?->id
                    ?? $session->subscription?->id
                    ?? $session->id;

                $subscription = $this->subscriptionService->subscribe($user, $plan, [
                    'method'           => 'stripe',
                    'transaction_id'   => $transactionId,
                    'amount'           => $plan->price,
                    'external_id'      => $session->subscription?->id,
                    'gateway_response' => [
                        'session_id'      => $session->id,
                        'payment_intent'  => $session->payment_intent?->id,
                        'subscription_id' => $session->subscription?->id,
                    ],
                ]);
            }

            session()->forget('stripe_checkout_plan_id');

            return view('subscription.payment-success', compact('plan'));

        } catch (\Exception $e) {
            Log::error('Payment success processing failed', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->route('subscription.my')
                ->with('info', 'Payment received. Your subscription will be activated shortly.');
        }
    }

    // ─── Payment Cancel ───────────────────────────────────────────────────────

    public function paymentCancel(): View
    {
        session()->forget('stripe_checkout_plan_id');
        return view('subscription.payment-cancel');
    }

    // ─── Stripe Webhook ───────────────────────────────────────────────────────

    /**
     * Handle Stripe webhook events for subscription lifecycle management.
     * Route: POST /stripe/webhook (no CSRF, no auth)
     */
    public function stripeWebhook(Request $request): \Illuminate\Http\Response
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret    = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed');
            return response('Signature verification failed', 400);
        }

        Log::info('Stripe webhook received', ['type' => $event->type]);

        match ($event->type) {
            'checkout.session.completed'         => $this->handleCheckoutCompleted($event->data->object),
            'invoice.payment_succeeded'          => $this->handleInvoicePaymentSucceeded($event->data->object),
            'invoice.payment_failed'             => $this->handleInvoicePaymentFailed($event->data->object),
            'customer.subscription.deleted'      => $this->handleSubscriptionDeleted($event->data->object),
            'customer.subscription.updated'      => $this->handleSubscriptionUpdated($event->data->object),
            default                              => null,
        };

        return response('Webhook handled', 200);
    }

    private function handleCheckoutCompleted(object $session): void
    {
        // Already handled in paymentSuccess, but webhook is the reliable path
        $userId = $session->metadata->user_id ?? null;
        $planId = $session->metadata->plan_id ?? null;

        if (!$userId || !$planId) {
            return;
        }

        $existingPayment = Payment::where('transaction_id', $session->id)->first();
        if ($existingPayment) {
            return; // Already processed
        }

        $user = \App\Models\User::find($userId);
        $plan = Plan::find($planId);

        if (!$user || !$plan) {
            return;
        }

        $this->subscriptionService->subscribe($user, $plan, [
            'method'           => 'stripe',
            'transaction_id'   => $session->payment_intent ?? $session->id,
            'amount'           => $plan->price,
            'external_id'      => $session->subscription ?? null,
            'gateway_response' => ['session_id' => $session->id],
        ]);

        Log::info('Subscription activated via webhook', ['user_id' => $userId, 'plan_id' => $planId]);
    }

    private function handleInvoicePaymentSucceeded(object $invoice): void
    {
        // Auto-renewal successful — renew the subscription
        $stripeSubId = $invoice->subscription;

        if (!$stripeSubId) {
            return;
        }

        $subscription = Subscription::where('external_subscription_id', $stripeSubId)->first();

        if ($subscription) {
            $this->subscriptionService->renew($subscription, [
                'method'         => 'stripe',
                'transaction_id' => $invoice->payment_intent,
                'amount'         => $invoice->amount_paid / 100,
            ]);

            Log::info('Subscription auto-renewed via webhook', ['subscription_id' => $subscription->id]);
        }
    }

    private function handleInvoicePaymentFailed(object $invoice): void
    {
        $stripeSubId = $invoice->subscription;
        $subscription = Subscription::where('external_subscription_id', $stripeSubId)->first();

        if ($subscription) {
            $subscription->update(['status' => 'grace']);
            Log::warning('Stripe payment failed, subscription moved to grace', [
                'subscription_id' => $subscription->id,
            ]);
        }
    }

    private function handleSubscriptionDeleted(object $stripeSubscription): void
    {
        $subscription = Subscription::where('external_subscription_id', $stripeSubscription->id)->first();

        if ($subscription) {
            $this->subscriptionService->cancel($subscription, immediately: true);
            Log::info('Subscription cancelled via Stripe webhook', ['subscription_id' => $subscription->id]);
        }
    }

    private function handleSubscriptionUpdated(object $stripeSubscription): void
    {
        // Handle plan changes, pauses etc. from Stripe dashboard
        Log::info('Stripe subscription updated', ['stripe_id' => $stripeSubscription->id]);
    }

    // ─── Manual Purchase (fallback) ───────────────────────────────────────────

    public function purchase(Request $request, Plan $plan): RedirectResponse
    {
        $request->validate([
            'payment_method' => 'required|string|in:manual,bank_transfer',
        ]);

        $user = Auth::user();

        try {
            $subscription = $this->subscriptionService->subscribe($user, $plan, [
                'method'     => $request->payment_method,
                'amount'     => $plan->price,
                'auto_renew' => false,
                'notes'      => 'Manual payment — pending admin verification',
            ]);

            return redirect()->route('subscription.my')
                ->with('success', 'Subscription request submitted. Access will be activated after payment verification.');

        } catch (\Exception $e) {
            report($e);
            return back()->with('error', 'Subscription failed. Please try again or contact support.');
        }
    }

    // ─── My Subscription ─────────────────────────────────────────────────────

    public function mySubscription(): View
    {
        $user         = Auth::user();
        $subscription = $user->subscription()->with('plan')->first();
        $payments     = $user->payments()->with('plan')->latest()->paginate(10);

        return view('subscription.my', compact('subscription', 'payments'));
    }

    // ─── Cancel ───────────────────────────────────────────────────────────────

    public function cancel(Request $request): RedirectResponse
    {
        $user         = Auth::user();
        $subscription = $user->currentSubscription();

        if (!$subscription) {
            return back()->with('error', 'No active subscription to cancel.');
        }

        // Cancel in Stripe if it's a Stripe subscription
        if ($subscription->external_subscription_id) {
            try {
                $this->stripe->subscriptions->cancel($subscription->external_subscription_id);
            } catch (\Exception $e) {
                Log::warning('Stripe cancellation failed', ['error' => $e->getMessage()]);
            }
        }

        $this->subscriptionService->cancel($subscription);

        return redirect()->route('subscription.my')
            ->with('success', 'Subscription cancelled. Access continues until ' . $subscription->ends_at?->format('M d, Y') . '.');
    }

    // ─── Renew ────────────────────────────────────────────────────────────────

    public function renew(): View|RedirectResponse
    {
        $user         = Auth::user();
        $subscription = $user->subscription()->with('plan')->first();

        if (!$subscription) {
            return redirect()->route('subscription.plans');
        }

        $stripeEnabled = !empty(config('services.stripe.key'));

        return view('subscription.renew', compact('subscription', 'stripeEnabled'));
    }

    public function processRenewal(Request $request): RedirectResponse
    {
        $user         = Auth::user();
        $subscription = $user->subscription()->with('plan')->first();

        if (!$subscription) {
            return redirect()->route('subscription.plans');
        }

        // If renewing via Stripe, redirect to checkout
        if ($request->payment_method === 'stripe') {
            return $this->createStripeCheckoutSession($request, $subscription->plan);
        }

        try {
            $this->subscriptionService->renew($subscription, [
                'method' => $request->payment_method ?? 'manual',
                'amount' => $subscription->plan->price,
            ]);

            return redirect()->route('subscription.my')
                ->with('success', 'Subscription renewed successfully!');

        } catch (\Exception $e) {
            report($e);
            return back()->with('error', 'Renewal failed. Please try again.');
        }
    }

    // ─── Invoice Download ─────────────────────────────────────────────────────

    public function downloadInvoice(Payment $payment): RedirectResponse
    {
        if ($payment->user_id !== Auth::id()) {
            abort(403);
        }

        // With barryvdh/laravel-dompdf installed:
        // $pdf = PDF::loadView('subscription.invoice-pdf', compact('payment'));
        // return $pdf->download('invoice-' . $payment->invoice_number . '.pdf');

        return back()->with('info', 'Install barryvdh/laravel-dompdf to enable PDF downloads.');
    }

    // ─── Expired Page ─────────────────────────────────────────────────────────

    public function expired(): View
    {
        $user         = Auth::user();
        $subscription = $user->subscription()->with('plan')->first();
        $plans        = Plan::active()->ordered()->get();

        return view('subscription.expired', compact('subscription', 'plans'));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getOrCreateStripeCustomer(\App\Models\User $user): string
    {
        // Cache the customer ID in user metadata or a dedicated column
        // For now we search by email
        $customers = $this->stripe->customers->search([
            'query' => "email:'{$user->email}'",
            'limit' => 1,
        ]);

        if (!empty($customers->data)) {
            return $customers->data[0]->id;
        }

        $customer = $this->stripe->customers->create([
            'email' => $user->email,
            'name'  => $user->name,
            'metadata' => ['user_id' => $user->id],
        ]);

        return $customer->id;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\Stripe\StripeClientFactory;
use App\Services\Stripe\WebhookProcessor;
use App\Services\Billing\TrialActivationService;
use App\Services\Stripe\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function checkout(
        string $name,
        Request $request,
        TrialActivationService $trialActivation,
        CheckoutService $checkoutService
    ) {
        $plan = Plan::where('slug', $name)->firstOrFail();

        if (! $plan->is_active) {
            toastr()->error('This plan is not available.');

            return redirect()->route('pricing.plans');
        }

        $user = $request->user();

        try {
            if ($plan->isTrial()) {
                $trialActivation->activate($user, $plan);
                toastr()->success('Your free trial is active. No credit card required.');

                return redirect()->route('checkout.success');
            }

            if (! in_array($plan->billing_cycle, ['monthly', 'yearly'], true)) {
                toastr()->error('Invalid plan billing cycle.');

                return redirect()->route('pricing.plans');
            }

            if ($user->hasActiveSubscription()) {
                toastr()->error('You already have an active subscription. Manage it from Billing.');

                return redirect()->route('billing.index');
            }

            $session = $checkoutService->createSubscriptionCheckout($user, $plan);

            return redirect()->away($session->url);
        } catch (\InvalidArgumentException $e) {
            toastr()->error($e->getMessage());

            return redirect()->route('pricing.plans');
        } catch (\Throwable $e) {
            Log::error('Checkout failed', [
                'user_id' => $user->id,
                'plan' => $plan->slug,
                'error' => $e->getMessage(),
            ]);
            toastr()->error('Unable to start checkout. Please try again.');

            return redirect()->route('pricing.plans');
        }
    }

    public function success(Request $request, StripeClientFactory $stripe, WebhookProcessor $processor)
    {
        $user = $request->user();
        $sessionId = $request->query('session_id');

        if ($sessionId && $user) {
            try {
                $session = $stripe->make()->checkout->sessions->retrieve($sessionId, [
                    'expand' => ['invoice', 'subscription'],
                ]);
                $belongsToUser = ((string) ($session->metadata->user_id ?? '') === (string) $user->id)
                    || ((string) ($session->client_reference_id ?? '') === (string) $user->id);

                if ($belongsToUser) {
                    // Dual-write: subscription + payment (works without webhook on localhost).
                    $processor->syncCheckoutSession($session);
                }
            } catch (\Throwable $e) {
                Log::warning('Checkout success sync failed', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $user?->refresh();
        $subscription = $user?->activeSubscription();
        $plan = $subscription?->plan;
        $latestPayment = $user?->payments()->where('status', 'paid')->first();

        return view('billing.checkout-success', compact('plan', 'subscription', 'latestPayment'));
    }
}

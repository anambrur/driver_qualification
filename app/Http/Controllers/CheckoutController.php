<?php

namespace App\Http\Controllers;

use App\Http\Requests\Billing\CheckoutRequest;
use App\Models\PlanPrice;
use App\Services\Billing\AccessStateResolver;
use App\Services\Billing\TrialActivationService;
use App\Services\Stripe\CheckoutService;
use App\Services\Stripe\StripeClientFactory;
use App\Services\Stripe\StripeEventProcessor;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Checkout and trials for the user's company (07 §5, §6.1). Routes need billing.manage.
 */
class CheckoutController extends Controller
{
    public function checkout(CheckoutRequest $request, CheckoutService $checkout): RedirectResponse
    {
        $user = $request->user();
        $price = PlanPrice::query()->findOrFail($request->validated('plan_price_id'));

        try {
            return redirect()->away($checkout->createSubscriptionCheckout($user->company, $user, $price)->url);
        } catch (InvalidArgumentException $e) {
            Toast::error($e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Checkout failed', ['company_id' => $user->company_id, 'plan_price_id' => $price->id, 'error' => $e->getMessage()]);
            Toast::error('Unable to start checkout. Please try again.');
        }

        return redirect()->route('pricing.plans');
    }

    public function trial(Request $request, TrialActivationService $trials): RedirectResponse
    {
        $user = $request->user();

        try {
            $trials->activate($user->company, $user);
            Toast::success('Your free trial is active. No credit card required.');

            return redirect()->route('checkout.success');
        } catch (InvalidArgumentException $e) {
            Toast::error($e->getMessage());

            return redirect()->route('pricing.plans');
        }
    }

    /**
     * Syncs the session only when it belongs to the user's company (07 §6.1): the dual write
     * that works without webhook forwarding on localhost.
     */
    public function success(Request $request, StripeClientFactory $stripe, StripeEventProcessor $processor): View
    {
        $company = $request->user()->company;
        $sessionId = $request->query('session_id');

        if (is_string($sessionId) && $sessionId !== '') {
            try {
                $session = $stripe->make()->checkout->sessions->retrieve($sessionId);
                $owners = array_filter([
                    (string) ($session->metadata->company_id ?? ''),
                    (string) ($session->client_reference_id ?? ''),
                ], fn (string $id) => $id !== '');

                // Every company id the session carries must be this company.
                if ($owners !== [] && array_diff($owners, [(string) $company->id]) === []) {
                    $processor->syncCheckoutSession($company, $session);
                }
            } catch (\Throwable $e) {
                Log::warning('Checkout success sync failed', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
            }
        }

        $subscription = AccessStateResolver::accessibleSubscription($company);
        $subscription?->load('planPrice.plan');
        $plan = $subscription?->planPrice?->plan;
        $latestPayment = $company->payments()->where('status', 'paid')->latest('paid_at')->first();

        return view('billing.checkout-success', compact('plan', 'subscription', 'latestPayment'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Payment;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService)
    {
    }

    /**
     * Show all available plans.
     */
    public function plans(): View
    {
        $plans = Plan::active()->ordered()->get();
        $user  = Auth::user();

        $currentSubscription = $user->currentSubscription();

        return view('subscription.plans', compact('plans', 'currentSubscription'));
    }

    /**
     * Show checkout page for a specific plan.
     */
    public function checkout(Plan $plan): View|RedirectResponse
    {
        if (!$plan->is_active) {
            return redirect()->route('subscription.plans')->with('error', 'This plan is not available.');
        }

        $user = Auth::user();

        // If already on this plan, redirect
        if ($user->subscribedTo($plan->slug)) {
            return redirect()->route('subscription.my')->with('info', 'You are already on this plan.');
        }

        return view('subscription.checkout', compact('plan'));
    }

    /**
     * Process a subscription purchase.
     * For real projects, integrate Stripe/PayPal here before calling subscribe().
     */
    public function purchase(Request $request, Plan $plan): RedirectResponse
    {
        $request->validate([
            'payment_method' => 'required|string|in:manual,stripe,paypal,card',
        ]);

        $user = Auth::user();

        try {
            /*
             * ── Stripe Integration Point ─────────────────────────────────────
             * $intent = StripeService::charge($user, $plan->price, $request->payment_token);
             * $paymentData['transaction_id'] = $intent->id;
             * $paymentData['gateway_response'] = $intent->toArray();
             * ────────────────────────────────────────────────────────────────
             */

            $paymentData = [
                'method'         => $request->payment_method,
                'transaction_id' => $request->transaction_id ?? null,
                'amount'         => $plan->price,
                'auto_renew'     => $request->boolean('auto_renew'),
            ];

            $subscription = $this->subscriptionService->subscribe($user, $plan, $paymentData);

            return redirect()->route('subscription.my')
                ->with('success', "You are now subscribed to the {$plan->name} plan!");

        } catch (\Exception $e) {
            report($e);
            return back()->with('error', 'Subscription failed. Please try again or contact support.');
        }
    }

    /**
     * Show current subscription details.
     */
    public function mySubscription(): View
    {
        $user         = Auth::user();
        $subscription = $user->subscription()->with('plan')->latest()->first();
        $payments     = $user->payments()->with('plan')->latest()->paginate(10);

        return view('subscription.my', compact('subscription', 'payments'));
    }

    /**
     * Cancel subscription.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $subscription = $user->currentSubscription();

        if (!$subscription) {
            return back()->with('error', 'No active subscription to cancel.');
        }

        $this->subscriptionService->cancel($subscription);

        return redirect()->route('subscription.my')
            ->with('success', 'Your subscription has been cancelled. You will retain access until ' . $subscription->ends_at?->format('M d, Y') . '.');
    }

    /**
     * Renewal page.
     */
    public function renew(): View|RedirectResponse
    {
        $user = Auth::user();
        $subscription = $user->subscription()->with('plan')->first();

        if (!$subscription) {
            return redirect()->route('subscription.plans');
        }

        return view('subscription.renew', compact('subscription'));
    }

    /**
     * Process renewal payment.
     */
    public function processRenewal(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $subscription = $user->subscription()->with('plan')->first();

        if (!$subscription) {
            return redirect()->route('subscription.plans');
        }

        try {
            $paymentData = [
                'method'         => $request->payment_method ?? 'manual',
                'transaction_id' => $request->transaction_id ?? null,
                'amount'         => $subscription->plan->price,
            ];

            $this->subscriptionService->renew($subscription, $paymentData);

            return redirect()->route('subscription.my')
                ->with('success', 'Your subscription has been renewed successfully!');

        } catch (\Exception $e) {
            report($e);
            return back()->with('error', 'Renewal failed. Please try again.');
        }
    }

    /**
     * Download invoice as PDF.
     */
    public function downloadInvoice(Payment $payment): \Illuminate\Http\Response|RedirectResponse
    {
        // Ensure the user owns this invoice
        if ($payment->user_id !== Auth::id()) {
            abort(403);
        }

        // Use a PDF library like barryvdh/laravel-dompdf
        // $pdf = PDF::loadView('subscription.invoice-pdf', compact('payment'));
        // return $pdf->download('invoice-' . $payment->invoice_number . '.pdf');

        return back()->with('info', 'PDF download requires a PDF library. See SubscriptionController@downloadInvoice.');
    }

    /**
     * Expired subscription page.
     */
    public function expired(): View
    {
        $user = Auth::user();
        $subscription = $user->subscription()->with('plan')->first();
        $plans = Plan::active()->ordered()->get();

        return view('subscription.expired', compact('subscription', 'plans'));
    }
}

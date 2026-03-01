<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    /**
     * My Subscription / Billing dashboard.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $cashierSubscription = $user->subscription('default');
        $plan = null;

        if ($cashierSubscription) {
            $plan = Plan::where('stripe_price_id', $cashierSubscription->stripe_price)->first();
        }

        $invoices = $user->invoices();

        return view('billing.index', [
            'subscription' => $cashierSubscription,
            'plan' => $plan,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Redirect to Stripe Billing Portal (manage payment method, cancel, invoices).
     */
    public function portal(Request $request): RedirectResponse
    {
        return $request->user()->redirectToBillingPortal(route('billing.index'));
    }

    /**
     * Download a specific invoice.
     */
    public function downloadInvoice(Request $request, string $id): mixed
    {
        return $request->user()->downloadInvoice($id, [
            'vendor' => config('app.name'),
            'product' => 'Subscription',
        ]);
    }

    /**
     * Cancel subscription at period end.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user->subscribed('default')) {
            return redirect()->route('billing.index')->with('error', 'No active subscription to cancel.');
        }

        $user->subscription('default')->cancel();

        return redirect()->route('billing.index')
            ->with('success', 'Your subscription will be cancelled at the end of the current billing period.');
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resume(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user->subscription('default')->onGracePeriod()) {
            return redirect()->route('billing.index')->with('error', 'Subscription cannot be resumed.');
        }

        $user->subscription('default')->resume();

        return redirect()->route('billing.index')
            ->with('success', 'Your subscription has been resumed.');
    }
}

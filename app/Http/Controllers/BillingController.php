<?php

namespace App\Http\Controllers;

use App\Services\Stripe\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription() ?? $user->subscriptions()->with('plan')->first();
        $plan = $subscription?->plan;
        $payments = $user->payments()->with('plan')->limit(25)->get();

        return view('billing.index', compact('subscription', 'plan', 'payments'));
    }

    public function portal(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        try {
            $session = $subscriptions->createBillingPortalSession(
                $request->user(),
                route('billing.index')
            );

            return redirect()->away($session->url);
        } catch (\Throwable $e) {
            Log::warning('Billing portal failed', ['error' => $e->getMessage()]);
            toastr()->error('Unable to open the billing portal right now.');

            return redirect()->route('billing.index');
        }
    }

    public function cancel(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscription = $request->user()->activeSubscription();

        if (! $subscription) {
            return redirect()->route('billing.index')->with('error', 'No active subscription to cancel.');
        }

        try {
            $subscriptions->cancelAtPeriodEnd($subscription);
            toastr()->success('Your subscription will end at the end of the current billing period.');
        } catch (\Throwable $e) {
            Log::warning('Cancel failed', ['error' => $e->getMessage()]);
            toastr()->error('Unable to cancel subscription.');
        }

        return redirect()->route('billing.index');
    }

    public function resume(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscription = $request->user()->subscriptions()->latest()->first();

        if (! $subscription || ! $subscription->onGracePeriod()) {
            return redirect()->route('billing.index')->with('error', 'Subscription cannot be resumed.');
        }

        try {
            $subscriptions->resume($subscription);
            toastr()->success('Your subscription has been resumed.');
        } catch (\Throwable $e) {
            Log::warning('Resume failed', ['error' => $e->getMessage()]);
            toastr()->error($e->getMessage() ?: 'Unable to resume subscription.');
        }

        return redirect()->route('billing.index');
    }
}

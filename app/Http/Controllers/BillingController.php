<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\Billing\AccessState;
use App\Services\Stripe\SubscriptionService;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * The company's billing (07 §6.3): one subscription shared by every user of the company.
 */
class BillingController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->company;
        $subscription = $company->subscriptions()->with('planPrice.plan')->latest('id')->first();
        $plan = $subscription?->planPrice?->plan;
        $payments = $company->payments()->with('plan')->latest('paid_at')->limit(25)->get();
        $accessState = AccessState::for($company);

        return view('billing.index', compact('subscription', 'plan', 'payments', 'accessState', 'company'));
    }

    public function portal(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        try {
            return redirect()->away($subscriptions->createBillingPortalSession($request->user()->company, route('billing.index'))->url);
        } catch (\Throwable $e) {
            Log::warning('Billing portal failed', ['error' => $e->getMessage()]);
            Toast::error('Unable to open the billing portal right now.');

            return redirect()->route('billing.index');
        }
    }

    public function cancel(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscription = $request->user()->company->subscriptions()->open()->first();

        if (! $subscription?->isAccessible() || $subscription->cancel_at_period_end) {
            return redirect()->route('billing.index')->with('error', 'No active subscription to cancel.');
        }

        try {
            $subscriptions->cancelAtPeriodEnd($subscription);
            AuditLog::record($request->user(), $subscription->company_id, 'subscription.cancel_requested', $subscription);
            Toast::success('Your subscription will end at the end of the current billing period.');
        } catch (\Throwable $e) {
            Log::warning('Cancel failed', ['error' => $e->getMessage()]);
            Toast::error('Unable to cancel subscription.');
        }

        return redirect()->route('billing.index');
    }

    public function resume(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $subscription = $request->user()->company->subscriptions()->open()->first();

        if (! $subscription?->canResume()) {
            return redirect()->route('billing.index')->with('error', 'Subscription cannot be resumed.');
        }

        try {
            $subscriptions->resume($subscription);
            AuditLog::record($request->user(), $subscription->company_id, 'subscription.resumed', $subscription);
            Toast::success('Your subscription has been resumed.');
        } catch (\Throwable $e) {
            Log::warning('Resume failed', ['error' => $e->getMessage()]);
            Toast::error('Unable to resume subscription.');
        }

        return redirect()->route('billing.index');
    }
}

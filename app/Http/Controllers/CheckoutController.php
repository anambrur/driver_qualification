<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{

    public function checkout(string $name, Request $request)
    {
        $plan = Plan::where('slug', $name)->firstOrFail();

        if (!$plan->is_active) {
            toastr()->error('This plan is not available.');
            return redirect()->route('pricing.plans');
        }

        $builder = $request->user()->newSubscription('default', $plan->stripe_price_id);

        // Apply trial ONLY when the plan explicitly has trial days.
        if ((int) ($plan->trial_days ?? 0) > 0) {
            $builder->trialDays((int) $plan->trial_days);
        } else {
            // Allow promo codes only for paid plans (prevents odd trial+promo combinations).
            $builder->allowPromotionCodes();
        }

        return $builder->checkout([
            'success_url' => route('checkout.success'),
            'cancel_url' => route('pricing.plans'),
        ]);
    }

    public function success(Request $request)
    {
        $plan = null;
        $subscription = $request->user()?->subscription('default');
        if ($subscription) {
            $plan = Plan::where('stripe_price_id', $subscription->stripe_price)->first();
        }

        return view('billing.checkout-success', compact('plan'));
    }
}

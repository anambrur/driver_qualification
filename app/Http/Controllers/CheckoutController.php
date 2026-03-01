<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{

    public function checkout($name, Request $request)
    {
        $plan = Plan::where('slug', $name)->first();
        $planPrice = $plan->stripe_price_id;


        return $request->user()
            ->newSubscription('default', $planPrice)
            ->trialDays(5)
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => route('checkout.success'),
                'cancel_url' => route('pricing.plans'),
            ]);
    }

    public function success()
    {
        return view('admin.plans.checkout-success');
    }
}

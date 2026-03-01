<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function plans(Request $request)
    {
        $plans = Plan::active()->ordered()->get();
        $currentSubscription = $request->user()?->subscription('default');
        $currentPlan = null;
        if ($currentSubscription) {
            $currentPlan = Plan::where('stripe_price_id', $currentSubscription->stripe_price)->first();
        }

        return view('billing.plans', compact('plans', 'currentSubscription', 'currentPlan'));
    }
}

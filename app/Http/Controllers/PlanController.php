<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function plans(Request $request)
    {
        $plans = Plan::active()->ordered()->get();
        $currentSubscription = $request->user()?->activeSubscription();
        $currentPlan = $currentSubscription?->plan;

        return view('billing.plans', compact('plans', 'currentSubscription', 'currentPlan'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\Billing\AccessStateResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function plans(Request $request): View
    {
        $plans = Plan::query()->forSale()->get();
        $company = $request->user()->company;
        $currentSubscription = AccessStateResolver::accessibleSubscription($company);
        $currentSubscription?->load('planPrice.plan');
        $currentPlan = $currentSubscription?->planPrice?->plan;
        $trialAvailable = $company->trial_used_at === null;

        return view('billing.plans', compact('plans', 'currentSubscription', 'currentPlan', 'trialAvailable'));
    }
}

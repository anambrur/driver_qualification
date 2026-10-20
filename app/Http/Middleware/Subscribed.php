<?php

namespace App\Http\Middleware;

use App\Services\Billing\AccessState;
use App\Support\Ui\Toast;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant pages need an access state other than none or blocked (07 §8). Read-only passes:
 * the company can still view and export; Gate::before denies its writes.
 */
class Subscribed
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user()?->company;
        $state = $company ? AccessState::for($company) : AccessState::None;

        if ($state->allowsTenantPages()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'An active subscription is required.', 'access_state' => $state->value], 402);
        }

        Toast::warning('An active subscription is required. Please choose a plan.');

        return redirect()->route('pricing.plans');
    }
}

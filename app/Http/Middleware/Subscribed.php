<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Subscribed
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->hasRole('super-admin')) {
            return $next($request);
        }

        if (! $request->user()?->hasActiveSubscription()) {
            if (function_exists('toastr')) {
                toastr()->warning('An active subscription is required. Please choose a plan.');
            }

            return redirect()->route('pricing.plans');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks access to protected routes if the user's subscription is expired.
 *
 * Usage in routes:
 *   Route::middleware(['auth', 'subscription'])->group(function () { ... });
 *
 * Usage in Kernel (app/Http/Kernel.php) or bootstrap/app.php:
 *   'subscription' => \App\Http\Middleware\CheckSubscription::class,
 */
class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        // Check if user has an active subscription
        if (!$user->hasActiveSubscription()) {
            // Log them out if their subscription expired while logged in
            $sub = $user->subscription;

            if ($sub && in_array($sub->status, ['expired', 'cancelled', 'suspended'])) {
                // Don't force logout — redirect to a clear "subscription expired" page
                return redirect()->route('subscription.expired')
                    ->with('warning', 'Your subscription has expired. Please renew to continue.');
            }

            // No subscription at all — redirect to plans page
            return redirect()->route('subscription.plans')
                ->with('info', 'Please subscribe to access this feature.');
        }

        // Inject subscription into request for easy access in controllers
        $request->merge(['_subscription' => $user->currentSubscription()]);

        return $next($request);
    }
}

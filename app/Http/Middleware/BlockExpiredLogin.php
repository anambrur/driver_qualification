<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks login for users with expired subscriptions.
 *
 * Register AFTER the auth middleware in your LoginController or
 * attach to the login route response via an event listener.
 *
 * ─── Option A: Via AuthenticatedSessionController (Breeze) ───────────────────
 *
 * Override the `store` method in App\Http\Controllers\Auth\AuthenticatedSessionController:
 *
 *   public function store(LoginRequest $request): RedirectResponse
 *   {
 *       $request->authenticate();
 *       $user = Auth::user();
 *
 *       if (!$user->hasActiveSubscription()) {
 *           Auth::logout();
 *           return back()->withErrors([
 *               'email' => 'Your subscription has expired. Please renew to log in.',
 *           ])->onlyInput('email');
 *       }
 *
 *       $request->session()->regenerate();
 *       return redirect()->intended(route('dashboard', absolute: false));
 *   }
 *
 * ─── Option B: Via Event Listener ────────────────────────────────────────────
 *
 * In App\Providers\EventServiceProvider (or AppServiceProvider in Laravel 12):
 *
 *   use Illuminate\Auth\Events\Login;
 *
 *   Event::listen(Login::class, function ($event) {
 *       $user = $event->user;
 *       if (!$user->hasActiveSubscription()) {
 *           Auth::logout();
 *           session()->invalidate();
 *           session()->regenerateToken();
 *           // Redirect handled by middleware on next request
 *       }
 *   });
 *
 * ─── Option C: Middleware after auth ─────────────────────────────────────────
 *
 * In bootstrap/app.php (Laravel 12):
 *
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->alias([
 *           'subscription'       => \App\Http\Middleware\CheckSubscription::class,
 *           'subscription.login' => \App\Http\Middleware\BlockExpiredLogin::class,
 *       ]);
 *   })
 *
 * Then in routes/web.php:
 *   Route::post('/login', [AuthenticatedSessionController::class, 'store'])
 *       ->middleware('subscription.login');
 */
class BlockExpiredLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // After login attempt, check if now authenticated
        if (Auth::check()) {
            $user = Auth::user();

            if (!$user->hasActiveSubscription()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => $this->buildErrorMessage($user),
                ])->onlyInput('email');
            }
        }

        return $response;
    }

    private function buildErrorMessage($user): string
    {
        $sub = $user->subscription;

        return match ($sub?->status) {
            'expired'   => 'Your subscription has expired. Please renew your plan to log in.',
            'cancelled' => 'Your subscription was cancelled. Please subscribe to a plan to continue.',
            'suspended' => 'Your account has been suspended. Please contact support.',
            default     => 'You do not have an active subscription. Please purchase a plan to log in.',
        };
    }
}

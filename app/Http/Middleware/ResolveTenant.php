<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the tenant context and Spatie team from the logged-in user's company (03 §4.1).
 * Runs before SubstituteBindings so route-model binding is already scoped. Every user of a
 * suspended or deactivated company is logged out, not just the owner.
 */
class ResolveTenant
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user) {
            return $next($request);
        }

        $company = $user->company;

        if ($company?->status !== 'active') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your company account is not active.'], 403);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Your company account is not active. Please contact support.',
            ]);
        }

        return app(TenantContext::class)->run($company->id, fn () => $next($request));
    }
}

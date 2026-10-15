<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a user who was set to `inactive` after logging in.
 * LoginRequest only checks the status at login time.
 */
class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // The tenant guard only: platform staff are checked by EnsurePlatformSession.
        if (Auth::guard('web')->user()?->status === 'inactive') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is inactive. Please contact administrator.',
            ]);
        }

        return $next($request);
    }
}

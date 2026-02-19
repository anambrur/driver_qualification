<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // ── Super-admin bypasses subscription check ────────────────────
        if (!$user->hasRole('super-admin') && !$user->hasActiveSubscription()) {
            Auth::logout();
            return back()->withErrors([
                'email' => match ($user->subscription?->status) {
                    'expired'   => 'Your subscription has expired. Please renew to log in.',
                    'cancelled' => 'Your subscription was cancelled. Please subscribe to log in.',
                    'suspended' => 'Your account has been suspended. Contact support.',
                    default     => 'You do not have an active subscription.',
                },
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

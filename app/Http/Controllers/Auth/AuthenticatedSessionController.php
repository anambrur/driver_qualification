<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\PlatformSupportSession;
use App\Models\PlatformUser;
use App\Services\Platform\SupportSessionService;
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

        $request->session()->regenerate();

        $user = $request->user();
        AuditLog::record($user, $user->company_id, 'auth.login');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, SupportSessionService $sessions): RedirectResponse
    {
        // Inside a support session: end it, and keep the staff member's platform login
        if (Auth::guard('web')->user()?->user_type === 'support') {
            $session = PlatformSupportSession::query()->find($request->session()->pull(SupportSessionService::SESSION_KEY));
            $staff = Auth::guard('platform')->user();
            if ($session !== null) {
                $sessions->end($session, 'ended by staff', $staff instanceof PlatformUser ? $staff : null);
            }
            Auth::guard('web')->logout();
            $request->session()->regenerateToken();

            return redirect()->route('platform.support-sessions.index')->with('success', 'Support session ended.');
        }

        $user = $request->user();
        if ($user !== null) {
            AuditLog::record($user, $user->company_id, 'auth.logout');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

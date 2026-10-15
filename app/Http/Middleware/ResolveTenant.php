<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\PlatformSupportSession;
use App\Models\PlatformUser;
use App\Models\User;
use App\Services\Platform\SupportSessionService;
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
    /** Routes about the logged-in user's own account; never for the shared support user. */
    private const SUPPORT_DENIED_ROUTES = [
        'profile.edit', 'profile.update', 'profile.destroy', 'password.update', 'password.confirm',
        'verification.notice', 'verification.send', 'verification.verify',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        // The platform area never runs as a tenant, even when the browser also has a tenant login.
        if (! $user || $request->is('platform', 'platform/*')) {
            return $next($request);
        }

        // Before the company check: a support login must end its session, not the whole PHP session
        if ($user->user_type === 'support') {
            return $this->supportSession($request, $user, $next);
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

    /**
     * The support user is only ever logged in by SupportSessionService::start(), for the staff
     * member of one active, unexpired session of its company (04 §4.3). Every request in the
     * session is audited (04 §4.4): method, route, path and status, never the body.
     *
     * @param  Closure(Request): (Response)  $next
     */
    private function supportSession(Request $request, User $user, Closure $next): Response
    {
        $sessions = app(SupportSessionService::class);
        $id = $request->session()->get(SupportSessionService::SESSION_KEY);
        $session = $id ? PlatformSupportSession::query()->find($id) : null;
        $staff = Auth::guard('platform')->user();

        $lastSeen = (int) $request->session()->get(EnsurePlatformSession::LAST_SEEN, time());
        $endReason = match (true) {
            $session?->status !== 'active' => null,
            $session->expires_at->isPast() => 'expired',
            $user->company?->status !== 'active' => 'company not active',
            // Tenant pages refresh the platform idle clock below, so check it before that
            time() - $lastSeen > config('auth.platform_idle_minutes') * 60 => 'platform login idle',
            default => null,
        };

        if ($endReason !== null) {
            $sessions->end($session, $endReason);
            $session->refresh();
        }

        $valid = $session?->status === 'active'
            && (int) $session->company_id === (int) $user->company_id
            && $staff instanceof PlatformUser && $staff->status === 'active'
            && (int) $staff->id === (int) $session->platform_user_id;

        if (! $valid) {
            Auth::guard('web')->logout();
            $request->session()->forget(SupportSessionService::SESSION_KEY);
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'The support session has ended.'], 401);
            }

            return redirect()->route('platform.support-sessions.index')->with('error', 'The support session has ended.');
        }

        // The support user's own account (profile, email, password) is never changed in a session
        if ($request->routeIs(...self::SUPPORT_DENIED_ROUTES)) {
            abort(403, 'Not available in a support session.');
        }

        // Work inside the session counts as platform activity (idle timeout)
        $request->session()->put(EnsurePlatformSession::LAST_SEEN, time());
        $status = 500;

        try {
            $response = app(TenantContext::class)->run($session->company_id, fn () => $next($request), 'support_session', $session->id);
            $status = $response->getStatusCode();

            return $response;
        } finally {
            AuditLog::write('platform_user', $staff->id, $session->company_id, 'support_session.request', $session, [
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'path' => '/'.ltrim($request->path(), '/'),
                'status' => $status,
            ], supportSessionId: $session->id);
        }
    }
}

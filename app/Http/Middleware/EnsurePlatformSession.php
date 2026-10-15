<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\PlatformUser;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every /platform request after `auth:platform` (04 §2): the platform guard is the default for
 * the request, the session ends after an idle timeout or when the staff member is disabled, and
 * the request runs in platform mode (no tenant: tenant models fail closed, team 0).
 */
class EnsurePlatformSession
{
    public const LAST_SEEN = 'platform.last_seen_at';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('platform');

        /** @var PlatformUser $user */
        $user = Auth::guard('platform')->user();
        $lastSeen = (int) $request->session()->get(self::LAST_SEEN, time());
        $idle = time() - $lastSeen > config('auth.platform_idle_minutes') * 60;

        if ($user->status !== 'active' || $idle) {
            AuditLog::record($user, null, $idle ? 'platform.session_expired' : 'platform.logout_inactive');
            self::logout($request);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('platform.login')->withErrors([
                'email' => $idle ? 'Your session expired. Please log in again.' : 'Your account is inactive.',
            ]);
        }

        $request->session()->put(self::LAST_SEEN, time());
        app(TenantContext::class)->setPlatform();
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        return $next($request);
    }

    /**
     * Ends the platform login only; a tenant login in the same browser is left alone.
     */
    public static function logout(Request $request): void
    {
        Auth::guard('platform')->logout();
        $request->session()->forget(self::LAST_SEEN);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
    }
}

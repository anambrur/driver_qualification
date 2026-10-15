<?php

namespace App\Http\Controllers\Platform\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePlatformSession;
use App\Http\Requests\Platform\LoginRequest;
use App\Models\AuditLog;
use App\Models\PlatformUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('platform.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        $request->session()->regenerate();
        $request->session()->put(EnsurePlatformSession::LAST_SEEN, time());
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::record($user, null, 'platform.login');

        // url.intended is shared with the tenant guard; only follow it within /platform
        $intended = (string) $request->session()->pull('url.intended');

        return redirect()->to(str_starts_with($intended, url('platform')) ? $intended : route('platform.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var PlatformUser $user */
        $user = $request->user('platform');
        AuditLog::record($user, null, 'platform.logout');
        EnsurePlatformSession::logout($request);

        return redirect()->route('platform.login');
    }
}

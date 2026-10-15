<?php

namespace App\Http\Controllers\Platform\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformUser;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Reset (and first-password) flow for platform staff, on the platform_users broker.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('platform.auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('platform_users')->sendResetLink(['email' => mb_strtolower(trim($request->string('email')))]);

        // The same answer whether or not the email is staff: no enumeration.
        return back()->with('status', 'If that email belongs to platform staff, a reset link is on its way.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('platform.auth.reset-password', ['token' => $token, 'email' => $request->string('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::broker('platform_users')->reset(
            ['email' => mb_strtolower(trim($request->string('email')))] + $request->only('password', 'password_confirmation', 'token'),
            function (PlatformUser $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
                AuditLog::record($user, null, 'platform.password_reset');
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('platform.login')->with('status', __($status))
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }
}

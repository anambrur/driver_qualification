<?php

namespace App\Http\Requests\Platform;

use App\Models\AuditLog;
use App\Models\PlatformUser;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Platform staff login (04 §2). No "remember me": platform sessions are short.
 */
class LoginRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): PlatformUser
    {
        $this->ensureIsNotRateLimited();

        $credentials = ['email' => mb_strtolower(trim($this->string('email'))), 'password' => $this->string('password')->value()];
        // Inactive staff get the same message as a wrong password: the form reveals nothing.
        $authenticated = Auth::guard('platform')->attemptWhen($credentials, fn (PlatformUser $user) => $user->status === 'active');

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());
            $known = PlatformUser::query()->where('email', $credentials['email'])->value('id');
            AuditLog::write('platform_user', $known, null, 'platform.login_failed', null, ['email' => $credentials['email']]);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($this->throttleKey());

        /** @var PlatformUser */
        return Auth::guard('platform')->user();
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey()), 'minutes' => 1]),
        ]);
    }

    private function throttleKey(): string
    {
        return 'platform|'.Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}

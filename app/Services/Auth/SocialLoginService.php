<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PlatformUser;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Company\CompanyProvisioner;
use App\Services\Platform\EmailIdentityService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Google / Facebook login, sign-up and account linking (P1-3). A provider login never links
 * itself to an existing account by email: the user signs in with the password and connects
 * the provider from the profile, so an unverified provider email can't take over an account.
 */
class SocialLoginService
{
    /** The provider profile waiting for the finish-sign-up form; never the token. */
    public const PENDING = 'social.pending';

    /** The user id that started "connect" from the profile. */
    public const CONNECT = 'social.connect';

    private const PENDING_MINUTES = 15;

    public function __construct(
        private readonly EmailIdentityService $emails,
        private readonly CompanyProvisioner $provisioner,
    ) {}

    /**
     * The guest callback. Logs in a known provider account, or keeps the profile of a new email
     * for the finish form and returns null.
     *
     * @throws ValidationException with a message for the login page
     */
    public function authenticate(string $provider, SocialiteUser $profile, Request $request): ?User
    {
        $account = SocialAccount::query()
            ->with('user.company')
            ->where('provider', $provider)
            ->where('provider_user_id', (string) $profile->getId())
            ->first();

        if ($account !== null) {
            $this->login($account->user, $provider, $request);

            return $account->user;
        }

        $email = $this->emails->normalize((string) $profile->getEmail());
        $label = SocialProviders::label($provider);

        if ($email === '') {
            throw $this->refuse("Your {$label} account did not share an email address. Register with your email and a password instead.");
        }

        // Tenant and platform emails get the same answer, so neither table is revealed (D3a)
        if (User::query()->where('email', $email)->exists() || PlatformUser::query()->where('email', $email)->exists()) {
            throw $this->refuse("An account with this email already exists. Sign in with your password, then connect {$label} from your profile.");
        }

        $request->session()->put(self::PENDING, [
            'provider' => $provider,
            'provider_user_id' => (string) $profile->getId(),
            'email' => $email,
            'name' => (string) $profile->getName(),
            // Facebook gives no verified flag, so its email is verified by our own link
            'email_verified' => $provider === 'google' && ($profile->user['email_verified'] ?? false) === true,
            'expires_at' => now()->addMinutes(self::PENDING_MINUTES)->getTimestamp(),
        ]);

        return null;
    }

    /**
     * @return array{provider: string, provider_user_id: string, email: string, name: string, email_verified: bool, expires_at: int}|null
     */
    public function pending(Request $request, string $provider): ?array
    {
        $pending = $request->session()->get(self::PENDING);

        return is_array($pending) && $pending['provider'] === $provider && $pending['expires_at'] > now()->getTimestamp()
            ? $pending
            : null;
    }

    /**
     * The same path as the password registration (10 §1): a new company with this user as the owner.
     *
     * @param  array{provider: string, provider_user_id: string, email: string, name: string, email_verified: bool, expires_at: int}  $pending
     *
     * @throws ValidationException
     */
    public function completeSignup(array $pending, string $name, string $companyName, string $timezone): User
    {
        try {
            $company = $this->claimCompany($pending, $name, $companyName, $timezone);
        } catch (UniqueConstraintViolationException) {
            // The provider account was linked meanwhile, e.g. the same sign-up finished in another tab
            throw $this->refuse('This '.SocialProviders::label($pending['provider']).' account is already connected. Sign in with it instead.');
        }

        return $company->owner;
    }

    /**
     * @param  array{provider: string, provider_user_id: string, email: string, name: string, email_verified: bool, expires_at: int}  $pending
     */
    private function claimCompany(array $pending, string $name, string $companyName, string $timezone): Company
    {
        return $this->emails->claim($pending['email'], function (string $email) use ($pending, $name, $companyName, $timezone) {
            $company = $this->provisioner->provision(
                ['company_name' => $companyName, 'email' => $email, 'timezone' => $timezone],
                // A random password: the owner sets a real one through the password reset
                ['name' => $name, 'email' => $email, 'password' => Hash::make(Str::random(40))]
                    + ($pending['email_verified'] ? ['email_verified_at' => now()] : []),
            );
            $account = $company->owner->socialAccounts()->create([
                'provider' => $pending['provider'],
                'provider_user_id' => $pending['provider_user_id'],
                'provider_email' => $email,
            ]);
            AuditLog::record($company->owner, $company->id, 'company.created', $company, ['source' => 'social:'.$pending['provider']]);
            AuditLog::record($company->owner, $company->id, 'auth.social_signup', $account, ['provider' => $pending['provider']]);

            return $company;
        });
    }

    /**
     * The same checks as the password login, plus the company status that ResolveTenant would
     * otherwise enforce on the next request.
     *
     * @throws ValidationException
     */
    public function login(User $user, string $provider, Request $request): void
    {
        $refusal = match (true) {
            $user->user_type === 'support' => 'Sign-in with '.SocialProviders::label($provider).' failed. Please try again.',
            $user->status === 'inactive' => 'Your account is inactive. Please contact administrator.',
            $user->company?->status !== 'active' => 'Your company account is not active. Please contact support.',
            default => null,
        };

        if ($refusal !== null) {
            AuditLog::write('user', $user->id, $user->company_id, 'auth.login_failed', null, ['provider' => $provider]);

            throw $this->refuse($refusal);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->forget(self::PENDING);
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::record($user, $user->company_id, 'auth.login', null, ['provider' => $provider]);
    }

    /**
     * @throws ValidationException
     */
    public function connect(User $user, string $provider, SocialiteUser $profile): void
    {
        $label = SocialProviders::label($provider);
        $linked = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', (string) $profile->getId())
            ->first(['id', 'user_id']);

        if ($linked !== null && (int) $linked->user_id === (int) $user->id) {
            return;
        }

        $alreadyLinked = $this->refuse("This {$label} account is already connected to another login.");
        if ($linked !== null) {
            throw $alreadyLinked;
        }

        if ($user->socialAccounts()->where('provider', $provider)->exists()) {
            throw $this->refuse("A {$label} account is already connected. Disconnect it first.");
        }

        try {
            $account = $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_user_id' => (string) $profile->getId(),
                'provider_email' => $profile->getEmail() !== null ? $this->emails->normalize($profile->getEmail()) : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw $alreadyLinked;
        }

        AuditLog::record($user, $user->company_id, 'auth.social_connected', $account, ['provider' => $provider]);
    }

    /**
     * @throws ValidationException
     */
    public function disconnect(User $user, string $provider): void
    {
        $account = $user->socialAccounts()->where('provider', $provider)->first();
        if ($account === null) {
            return;
        }

        if ($user->socialAccounts()->count() === 1 && $this->neverSetPassword($user)) {
            throw $this->refuse('Set a password before you disconnect your last sign-in provider.');
        }

        $account->delete();
        AuditLog::record($user, $user->company_id, 'auth.social_disconnected', $account, ['provider' => $provider]);
    }

    /**
     * A social sign-up gets a random password; it counts as set once the user resets or changes it.
     */
    public function neverSetPassword(User $user): bool
    {
        $audits = fn () => AuditLog::query()->where('actor_type', 'user')->where('actor_id', $user->id);

        return $audits()->where('action', 'auth.social_signup')->exists()
            && ! $audits()->whereIn('action', ['auth.password_reset', 'auth.password_changed'])->exists();
    }

    private function refuse(string $message): ValidationException
    {
        return ValidationException::withMessages(['email' => $message]);
    }
}

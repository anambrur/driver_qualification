<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\FinishSocialSignupRequest;
use App\Services\Auth\SocialLoginService;
use App\Services\Auth\SocialProviders;
use App\Support\Ui\Toast;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Google / Facebook sign-in and sign-up (P1-3, 10 §1). The callback also finishes "connect"
 * started from the profile, since a provider app has one redirect URI.
 */
class SocialLoginController extends Controller
{
    public function __construct(
        private readonly SocialLoginService $social,
        private readonly SocialProviders $providers,
    ) {}

    public function redirect(string $provider): SymfonyRedirectResponse
    {
        $this->ensureEnabled($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureEnabled($provider);
        $label = SocialProviders::label($provider);
        $connectFor = $request->session()->pull(SocialLoginService::CONNECT);
        $user = $request->user();

        try {
            $profile = Socialite::driver($provider)->user();
        } catch (InvalidArgumentException|GuzzleException $e) {
            // Invalid state, a denied consent screen or a provider error
            Log::info("Social login callback failed ({$provider}): ".$e::class);
            $message = "Sign-in with {$label} failed. Please try again.";

            if ($user !== null) {
                Toast::error($message);

                return redirect()->route($connectFor !== null ? 'profile.edit' : 'dashboard');
            }

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        if ($user !== null) {
            if ($connectFor === null || (int) $connectFor !== $user->id) {
                return redirect()->route('dashboard');
            }

            try {
                $this->social->connect($user, $provider, $profile);
                Toast::success("{$label} account connected.");
            } catch (ValidationException $e) {
                Toast::error($e->getMessage());
            }

            return redirect()->route('profile.edit');
        }

        try {
            $loggedIn = $this->social->authenticate($provider, $profile, $request);
        } catch (ValidationException $e) {
            return redirect()->route('login')->withErrors($e->errors());
        }

        return $loggedIn !== null
            ? redirect()->intended(route('dashboard', absolute: false))
            : redirect()->route('auth.social.finish', $provider);
    }

    /**
     * The short "finish sign-up" form for a new email.
     */
    public function create(Request $request, string $provider): View|RedirectResponse
    {
        $this->ensureEnabled($provider);
        $pending = $this->social->pending($request, $provider);

        if ($pending === null) {
            return $this->expired();
        }

        return view('auth.social-finish', [
            'provider' => $provider,
            'label' => SocialProviders::label($provider),
            'pending' => $pending,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(FinishSocialSignupRequest $request, string $provider): RedirectResponse
    {
        $this->ensureEnabled($provider);
        $pending = $this->social->pending($request, $provider);

        if ($pending === null) {
            return $this->expired();
        }

        try {
            $user = $this->social->completeSignup($pending, $request->validated('name'), $request->validated('company_name'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Social registration failed: '.$e->getMessage());
            Toast::error('An error occurred during registration. Please try again.');

            return back()->withInput();
        }

        $request->session()->forget(SocialLoginService::PENDING);
        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect(route('dashboard', absolute: false));
    }

    private function expired(): RedirectResponse
    {
        return redirect()->route('register')->withErrors(['email' => 'Your sign-up session has expired. Please try again.']);
    }

    private function ensureEnabled(string $provider): void
    {
        abort_unless($this->providers->isEnabled($provider), 404);
    }
}

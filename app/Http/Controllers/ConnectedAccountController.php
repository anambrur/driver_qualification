<?php

namespace App\Http\Controllers;

use App\Services\Auth\SocialLoginService;
use App\Services\Auth\SocialProviders;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Profile > Connected accounts: link or unlink the user's own Google / Facebook login (P1-3).
 * SocialLoginController::callback() finishes the link.
 */
class ConnectedAccountController extends Controller
{
    public function store(Request $request, SocialProviders $providers, string $provider): SymfonyRedirectResponse
    {
        abort_unless($providers->isEnabled($provider), 404);
        $request->session()->put(SocialLoginService::CONNECT, $request->user()->id);

        return Socialite::driver($provider)->redirect();
    }

    public function destroy(Request $request, SocialLoginService $social, string $provider): RedirectResponse
    {
        try {
            $social->disconnect($request->user(), $provider);
            Toast::success(SocialProviders::label($provider).' account disconnected.');
        } catch (ValidationException $e) {
            Toast::error($e->getMessage());
        }

        return redirect()->route('profile.edit');
    }
}

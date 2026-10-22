{{-- P1-3: link or unlink the user's own Google / Facebook login --}}
<div class="grid gap-4">
    @if ($neverSetPassword)
        <x-ui.alert tone="warning" title="You haven't set a password yet">
            You signed up with a sign-in provider. You need a password to disconnect it, change your password or close the account.
            To set one, sign out and choose "Forgot password?" on the sign-in page.
        </x-ui.alert>
    @endif

    <ul class="divide-y divide-border rounded-lg border border-border">
        {{-- Enabled providers, plus any linked one the platform has since switched off, so it can be removed --}}
        @foreach ($socialProviders + $socialAccounts->mapWithKeys(fn ($account) => [$account->provider => \App\Services\Auth\SocialProviders::label($account->provider)])->all() as $provider => $label)
            @php($account = $socialAccounts->get($provider))
            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div class="flex items-center gap-3">
                    @include('auth.partials.provider-logo', ['provider' => $provider])
                    <div class="grid">
                        <span class="font-medium">{{ $label }}</span>
                        <span class="text-sm text-muted-foreground">{{ $account ? ($account->provider_email ?? 'Connected') : 'Not connected' }}</span>
                    </div>
                </div>
                @if ($account)
                    <form method="POST" action="{{ route('profile.social.disconnect', $provider) }}"
                        data-confirm="Disconnect {{ $label }}?" data-confirm-button="Disconnect"
                        data-confirm-text="You can no longer sign in with this {{ $label }} account. Make sure you know your password.">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="outline" size="sm" icon="unlink">Disconnect</x-ui.button>
                    </form>
                @elseif (isset($socialProviders[$provider]))
                    <form method="POST" action="{{ route('profile.social.connect', $provider) }}">
                        @csrf
                        <x-ui.button type="submit" variant="outline" size="sm" icon="link">Connect</x-ui.button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
</div>

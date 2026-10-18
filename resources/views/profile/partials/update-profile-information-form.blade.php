<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}" class="grid gap-6">
    @csrf
    @method('patch')

    <x-ui.field :label="__('Name')" name="name" for="name">
        <x-ui.input id="name" name="name" :value="$user->name" required autofocus autocomplete="name" />
    </x-ui.field>

    <x-ui.field :label="__('Email')" name="email" for="email">
        <x-ui.input id="email" name="email" type="email" :value="$user->email" required autocomplete="username" />

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <p class="text-sm text-muted-foreground">
                {{ __('Your email address is unverified.') }}
                <button form="send-verification" class="font-medium text-foreground underline underline-offset-4">
                    {{ __('Click here to re-send the verification email.') }}
                </button>
            </p>

            @if (session('status') === 'verification-link-sent')
                <p class="text-sm font-medium text-success">
                    {{ __('A new verification link has been sent to your email address.') }}
                </p>
            @endif
        @endif
    </x-ui.field>

    <div class="flex items-center gap-4">
        <x-ui.button type="submit">{{ __('Save') }}</x-ui.button>

        @if (session('status') === 'profile-updated')
            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                class="text-sm text-muted-foreground">{{ __('Saved.') }}</p>
        @endif
    </div>
</form>

<x-layout.auth title="Sign in" heading="Sign in to your account"
    description="Sign in to manage driver qualification files, DOT compliance documents and expiry alerts.">
    <x-slot:intro>
        Don't have an account?
        <a href="{{ route('register') }}" class="font-medium text-primary underline-offset-4 hover:underline">Create one free</a>
    </x-slot:intro>

    <form method="POST" action="{{ route('login') }}" class="grid gap-5">
        @csrf

        <x-ui.field label="Email address" name="email">
            <x-ui.input type="email" name="email" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="you@company.com" />
        </x-ui.field>

        <x-ui.field name="password">
            <div class="flex items-center justify-between gap-2">
                <label for="f-password" class="label">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary underline-offset-4 hover:underline">Forgot password?</a>
                @endif
            </div>
            <x-ui.password required autocomplete="current-password" />
        </x-ui.field>

        <x-ui.checkbox name="remember" label="Remember me" />

        <x-ui.button type="submit" variant="primary" class="w-full">Sign in</x-ui.button>
    </form>
</x-layout.auth>

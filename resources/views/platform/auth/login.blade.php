<x-layout.auth area="platform" title="Platform sign in" heading="Platform staff login">
    <x-slot:intro>For platform staff only. Company users sign in at the main login page.</x-slot:intro>

    <form method="POST" action="{{ route('platform.login.store') }}" class="grid gap-5">
        @csrf

        <x-ui.field label="Email address" name="email">
            <x-ui.input type="email" name="email" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" />
        </x-ui.field>

        <x-ui.field name="password">
            <div class="flex items-center justify-between gap-2">
                <label for="f-password" class="label">Password</label>
                <a href="{{ route('platform.password.request') }}" class="text-sm font-medium text-primary underline-offset-4 hover:underline">Forgot your password?</a>
            </div>
            <x-ui.password required autocomplete="current-password" />
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" class="w-full">Log in</x-ui.button>
    </form>
</x-layout.auth>

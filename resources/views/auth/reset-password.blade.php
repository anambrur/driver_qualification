<x-layout.auth title="Reset password" heading="Choose a new password">
    <form method="POST" action="{{ route('password.store') }}" class="grid gap-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ui.field label="Email address" name="email">
            <x-ui.input type="email" name="email" :value="$request->email" required autofocus autocomplete="username" />
        </x-ui.field>

        <x-ui.field label="New password" name="password">
            <x-ui.password required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.field label="Confirm password" name="password_confirmation">
            <x-ui.password name="password_confirmation" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" class="w-full">Reset password</x-ui.button>
    </form>
</x-layout.auth>

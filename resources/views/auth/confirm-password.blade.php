<x-layout.auth title="Confirm password" heading="Confirm your password">
    <x-slot:intro>This is a secure area of the application. Please confirm your password before continuing.</x-slot:intro>

    <form method="POST" action="{{ route('password.confirm') }}" class="grid gap-5">
        @csrf

        <x-ui.field label="Password" name="password">
            <x-ui.password required autofocus autocomplete="current-password" />
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" class="w-full">Confirm</x-ui.button>
    </form>
</x-layout.auth>

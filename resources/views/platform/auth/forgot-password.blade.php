<x-layout.auth area="platform" title="Platform password reset" heading="Reset your staff password">
    <x-slot:intro>Enter your platform staff email and we will send you a link to choose a new password.</x-slot:intro>

    <form method="POST" action="{{ route('platform.password.email') }}" class="grid gap-5">
        @csrf

        <x-ui.field label="Email address" name="email">
            <x-ui.input type="email" name="email" required autofocus autocomplete="username" />
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" class="w-full">Email password reset link</x-ui.button>
    </form>

    <x-slot:footer>
        <a href="{{ route('platform.login') }}" class="font-medium text-primary underline-offset-4 hover:underline">Back to staff login</a>
    </x-slot:footer>
</x-layout.auth>

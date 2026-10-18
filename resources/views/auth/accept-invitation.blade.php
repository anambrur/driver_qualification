<x-layout.auth title="Accept invitation" :heading="'Join '.$companyName">
    <x-slot:intro>You have been invited to join {{ $companyName }}. Choose your name and password to continue.</x-slot:intro>

    <form method="POST" action="{{ route('invitations.accept', $token) }}" class="grid gap-5">
        @csrf

        <x-ui.field label="Email address" name="email">
            <x-ui.input type="email" id="f-email" :value="$email" disabled autocomplete="username" />
        </x-ui.field>

        <x-ui.field label="Name" name="name">
            <x-ui.input name="name" required autofocus autocomplete="name" />
        </x-ui.field>

        <x-ui.field label="Password" name="password">
            <x-ui.password required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.field label="Confirm password" name="password_confirmation">
            <x-ui.password name="password_confirmation" required autocomplete="new-password" />
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" class="w-full">Accept invitation</x-ui.button>
    </form>
</x-layout.auth>

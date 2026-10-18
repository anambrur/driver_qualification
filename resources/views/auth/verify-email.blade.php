<x-layout.auth title="Verify email" heading="Verify your email address" :status="false">
    <x-slot:intro>Thanks for signing up! Before getting started, please verify your email address by clicking the link we just emailed to you. If you didn't receive the email, we will gladly send you another.</x-slot:intro>

    @if (session('status') == 'verification-link-sent')
        <x-ui.alert tone="success" class="mb-6">A new verification link has been sent to the email address you provided during registration.</x-ui.alert>
    @endif

    <div class="grid gap-3">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button type="submit" variant="primary" class="w-full">Resend verification email</x-ui.button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ui.button type="submit" variant="ghost" class="w-full">Log out</x-ui.button>
        </form>
    </div>
</x-layout.auth>

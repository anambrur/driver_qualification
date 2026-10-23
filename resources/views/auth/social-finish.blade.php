<x-layout.auth title="Finish sign-up" heading="Finish creating your account"
    description="Add your company name and time zone to finish creating your account.">
    <x-slot:intro>
        Signing up with {{ $label }} as <span class="font-medium text-foreground">{{ $pending['email'] }}</span>.
        <a href="{{ route('register') }}" class="font-medium text-primary underline-offset-4 hover:underline">Use another way</a>
    </x-slot:intro>

    <form method="POST" action="{{ route('auth.social.finish', $provider) }}" class="grid gap-5">
        @csrf

        <x-ui.field label="Email address" name="email">
            <x-ui.input type="email" name="email" :value="$pending['email']" readonly disabled />
        </x-ui.field>

        <x-ui.field label="Full name" name="name">
            <x-ui.input name="name" :value="$pending['name']" required autocomplete="name" placeholder="John Doe" />
        </x-ui.field>

        <x-ui.field label="Company name" name="company_name">
            <x-ui.input name="company_name" required autofocus autocomplete="organization" placeholder="Acme Trucking LLC" />
        </x-ui.field>

        <x-ui.field label="Company time zone" name="timezone">
            <x-ui.select name="timezone" required :options="\App\Support\TimeZones::OPTIONS" :value="\App\Support\TimeZones::preselected()" />
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" class="w-full">Create account</x-ui.button>

        @include('auth.partials.terms')
    </form>
</x-layout.auth>

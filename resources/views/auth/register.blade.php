@php($trialDays = settings('trial_plan_id') ? (int) settings('trial_days') : 0)
<x-layout.auth title="Create your account" heading="Create your account"
    description="Create your account to start managing driver qualification files and stay DOT audit-ready.">
    <x-slot:intro>
        Already have an account?
        <a href="{{ route('login') }}" class="font-medium text-primary underline-offset-4 hover:underline">Sign in</a>
    </x-slot:intro>

    <x-slot:aside>
        <p class="text-3xl font-semibold leading-tight tracking-tight xl:text-4xl">
            Start managing your fleet <span class="text-[color-mix(in_oklch,var(--primary)_30%,white)]">in minutes.</span>
        </p>
        <ul class="mt-8 space-y-3">
            @foreach (array_filter([
                $trialDays > 0 ? $trialDays.'-day free trial' : null,
                'Complete DQF management tools',
                'Automated document tracking',
                'Audit-ready reports',
            ]) as $benefit)
                <li class="flex items-center gap-3 text-sm font-medium text-white/90">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-background/10">
                        <x-ui.icon name="check" class="size-3.5 text-[color-mix(in_oklch,var(--primary)_30%,white)]" />
                    </span>
                    {{ $benefit }}
                </li>
            @endforeach
        </ul>
    </x-slot:aside>

    <form method="POST" action="{{ route('register') }}" class="grid gap-5" x-data="{
        password: '',
        get strength() {
            let s = 0;
            if (this.password.length >= 8) s++;
            if (/[A-Z]/.test(this.password)) s++;
            if (/[0-9]/.test(this.password)) s++;
            if (/[^A-Za-z0-9]/.test(this.password)) s++;
            return s;
        },
        get strengthLabel() { return ['', 'Weak', 'Fair', 'Good', 'Strong'][this.strength]; },
        get strengthColor() { return ['bg-muted', 'bg-destructive', 'bg-warning', 'bg-info', 'bg-success'][this.strength]; },
    }">
        @csrf

        <x-ui.field label="Full name" name="name">
            <x-ui.input name="name" required autofocus autocomplete="name" placeholder="John Doe" />
        </x-ui.field>

        <x-ui.field label="Company name" name="company_name">
            <x-ui.input name="company_name" required autocomplete="organization" placeholder="Acme Trucking LLC" />
        </x-ui.field>

        <x-ui.field label="Email address" name="email">
            <x-ui.input type="email" name="email" required autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="you@company.com" />
        </x-ui.field>

        <x-ui.field label="Password" name="password">
            <x-ui.password required autocomplete="new-password" placeholder="Min 8 characters" x-model="password" />
            <div x-show="password.length > 0" x-cloak class="grid gap-1" aria-live="polite">
                <div class="flex gap-1" aria-hidden="true">
                    <template x-for="i in 4" :key="i">
                        <div class="h-1 flex-1 rounded-full transition-colors" :class="i <= strength ? strengthColor : 'bg-muted'"></div>
                    </template>
                </div>
                <p class="text-xs text-muted-foreground">Strength: <span x-text="strengthLabel"></span></p>
            </div>
        </x-ui.field>

        <x-ui.field label="Confirm password" name="password_confirmation">
            <x-ui.password name="password_confirmation" required autocomplete="new-password" placeholder="Repeat password" />
        </x-ui.field>

        <x-ui.button type="submit" variant="primary" class="w-full">Create account</x-ui.button>
    </form>
</x-layout.auth>

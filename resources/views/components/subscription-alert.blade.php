{{-- Company billing banner (07 §4.3, §9): grace, read-only, or access ending soon --}}
@auth('web')
    @php
        $company = auth('web')->user()->company;
        $accessState = $company ? \App\Services\Billing\AccessState::for($company) : null;
        $sub = $company ? app(\App\Services\Billing\AccessStateResolver::class)->openSubscription($company) : null;
        $readOnlyAt = $sub?->readOnlyAt(\App\Services\Billing\AccessStateResolver::graceDays());
        $daysRemaining = $readOnlyAt ? (int) now()->startOfDay()->diffInDays($readOnlyAt->copy()->startOfDay(), false) : null;
        $isExpiringSoon = $accessState === \App\Services\Billing\AccessState::Full && $daysRemaining !== null && $daysRemaining >= 0 && $daysRemaining <= 7;
        $banner = match (true) {
            $accessState === \App\Services\Billing\AccessState::ReadOnly => ['danger', 'lock', 'Renew'],
            $accessState === \App\Services\Billing\AccessState::Grace => ['warning', 'triangle-alert', 'Billing'],
            $isExpiringSoon => ['warning', 'triangle-alert', 'Renew'],
            default => null,
        };
    @endphp

    @if ($banner)
        <div class="mx-auto w-full max-w-screen-2xl px-4 pt-4 sm:px-6 lg:px-8">
            <x-ui.alert :tone="$banner[0]" :icon="$banner[1]">
                <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p>
                        @if ($accessState === \App\Services\Billing\AccessState::ReadOnly)
                            Your company's subscription has ended. You can view and export records, but not add or change them.
                        @elseif ($accessState === \App\Services\Billing\AccessState::Grace)
                            Your company's last payment failed. Update the payment method by
                            <strong>{{ $readOnlyAt?->format('M d, Y') }}</strong> to keep full access.
                        @else
                            Your company's <strong>{{ $sub->planName() }}</strong> subscription ends in
                            <strong>{{ $daysRemaining }} day{{ $daysRemaining !== 1 ? 's' : '' }}</strong>.
                        @endif
                    </p>
                    <a href="{{ route('pricing.plans') }}" class="shrink-0 text-sm font-medium underline underline-offset-4">{{ $banner[2] }} →</a>
                </div>
            </x-ui.alert>
        </div>
    @endif
@endauth

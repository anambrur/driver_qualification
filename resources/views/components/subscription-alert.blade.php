{{-- Company billing banner (07 §4.3, §9): grace, read-only, or access ending soon --}}
@auth('web')
    @php
        $company = auth('web')->user()->company;
        $accessState = $company ? \App\Services\Billing\AccessState::for($company) : null;
        $sub = $company ? app(\App\Services\Billing\AccessStateResolver::class)->openSubscription($company) : null;
        $readOnlyAt = $sub?->readOnlyAt(\App\Services\Billing\AccessStateResolver::graceDays());
        $daysRemaining = $readOnlyAt ? (int) now()->startOfDay()->diffInDays($readOnlyAt->copy()->startOfDay(), false) : null;
        $isExpiringSoon = $accessState === \App\Services\Billing\AccessState::Full && $daysRemaining !== null && $daysRemaining >= 0 && $daysRemaining <= 7;
    @endphp

    @if ($accessState === \App\Services\Billing\AccessState::ReadOnly)
        <div class="bg-red-50 dark:bg-red-900/20 border-b border-red-200 dark:border-red-800 px-4 py-2">
            <div class="flex items-center justify-between max-w-screen-xl mx-auto">
                <div class="flex items-center gap-2 text-red-800 dark:text-red-200 text-sm">
                    <i class="fas fa-lock text-red-500"></i>
                    <span>Your company's subscription has ended. You can view and export records, but not add or change them.</span>
                </div>
                <a href="{{ route('pricing.plans') }}"
                   class="text-xs font-semibold text-red-800 dark:text-red-200 underline hover:text-red-900 whitespace-nowrap ml-4">
                    Renew →
                </a>
            </div>
        </div>
    @elseif ($accessState === \App\Services\Billing\AccessState::Grace)
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border-b border-yellow-200 dark:border-yellow-800 px-4 py-2">
            <div class="flex items-center justify-between max-w-screen-xl mx-auto">
                <div class="flex items-center gap-2 text-yellow-800 dark:text-yellow-200 text-sm">
                    <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                    <span>
                        Your company's last payment failed. Update the payment method by
                        <strong>{{ $readOnlyAt?->format('M d, Y') }}</strong> to keep full access.
                    </span>
                </div>
                <a href="{{ route('pricing.plans') }}"
                   class="text-xs font-semibold text-yellow-800 dark:text-yellow-200 underline hover:text-yellow-900 whitespace-nowrap ml-4">
                    Billing →
                </a>
            </div>
        </div>
    @elseif ($isExpiringSoon)
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border-b border-yellow-200 dark:border-yellow-800 px-4 py-2">
            <div class="flex items-center justify-between max-w-screen-xl mx-auto">
                <div class="flex items-center gap-2 text-yellow-800 dark:text-yellow-200 text-sm">
                    <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                    <span>
                        Your company's <strong>{{ $sub->planName() }}</strong> subscription ends in
                        <strong>{{ $daysRemaining }} day{{ $daysRemaining !== 1 ? 's' : '' }}</strong>.
                    </span>
                </div>
                <a href="{{ route('pricing.plans') }}"
                   class="text-xs font-semibold text-yellow-800 dark:text-yellow-200 underline hover:text-yellow-900 whitespace-nowrap ml-4">
                    Renew →
                </a>
            </div>
        </div>
    @endif
@endauth

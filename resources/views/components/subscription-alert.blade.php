{{-- In-app subscription expiry / grace banner --}}
@auth
    @php
        $user = auth()->user();
        $sub = $user?->activeSubscription() ?? $user?->subscriptions()->with('plan')->first();
        $plan = $sub?->plan;
        $daysRemaining = $sub?->daysUntilAccessEnds();
        $isExpiringSoon = $sub && $sub->isEndingSoon();
        $onGrace = $sub && $sub->onGracePeriod();
    @endphp

    @if($sub && $plan && $isExpiringSoon && !$user->hasRole('super-admin'))
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border-b border-yellow-200 dark:border-yellow-800 px-4 py-2">
            <div class="flex items-center justify-between max-w-screen-xl mx-auto">
                <div class="flex items-center gap-2 text-yellow-800 dark:text-yellow-200 text-sm">
                    <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                    <span>
                        Your <strong>{{ $plan->name }}</strong> subscription expires in
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

    @if($sub && $onGrace && !$isExpiringSoon && !$user->hasRole('super-admin'))
        <div class="bg-red-50 dark:bg-red-900/20 border-b border-red-200 dark:border-red-800 px-4 py-2">
            <div class="flex items-center justify-between max-w-screen-xl mx-auto">
                <div class="flex items-center gap-2 text-red-800 dark:text-red-200 text-sm">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span>
                        Your subscription is cancelling. Access ends
                        <strong>{{ $sub->accessEndsAt()?->format('M d, Y') }}</strong>.
                    </span>
                </div>
                <a href="{{ route('billing.index') }}"
                   class="text-xs font-semibold text-red-800 dark:text-red-200 underline hover:text-red-900 whitespace-nowrap ml-4">
                    Manage Billing →
                </a>
            </div>
        </div>
    @endif
@endauth

{{--
=====================================================================
resources/views/components/subscription-alert.blade.php

Add this inside your main layout, AFTER the opening <body> tag or
at the top of your admin layout content area:

    <x-subscription-alert />
=====================================================================
--}}

@auth
    @php
        $user = auth()->user();
        $sub = $user->subscription('default');
        $plan = $sub ? \App\Models\Plan::where('stripe_price_id', $sub->stripe_price)->first() : null;
        $daysRemaining = $sub && $sub->ends_at ? now()->diffInDays($sub->ends_at, false) : null;
        $isExpiringSoon = $daysRemaining !== null && $daysRemaining <= 7 && $daysRemaining > 0;
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
                <a href="{{ route('billing.portal') }}"
                   class="text-xs font-semibold text-yellow-800 dark:text-yellow-200 underline hover:text-yellow-900 whitespace-nowrap ml-4">
                    Manage Billing →
                </a>
            </div>
        </div>
    @endif

    @if($sub && $sub->onGracePeriod() && !$user->hasRole('super-admin'))
        <div class="bg-red-50 dark:bg-red-900/20 border-b border-red-200 dark:border-red-800 px-4 py-2">
            <div class="flex items-center justify-between max-w-screen-xl mx-auto">
                <div class="flex items-center gap-2 text-red-800 dark:text-red-200 text-sm">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span>
                        Your subscription is cancelling. Access ends
                        <strong>{{ $sub->ends_at?->format('M d') }}</strong>.
                    </span>
                </div>
                <a href="{{ route('billing.index') }}"
                   class="text-xs font-semibold text-red-800 dark:text-red-200 underline hover:text-red-900 whitespace-nowrap ml-4">
                    Resume Subscription →
                </a>
            </div>
        </div>
    @endif
@endauth
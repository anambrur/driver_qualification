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
        $sub  = $user->currentSubscription();
    @endphp

    @if($sub && $sub->isExpiringSoon(7) && !$user->hasRole('super-admin'))
        <div class="bg-yellow-50 border-b border-yellow-200 px-4 py-2">
            <div class="flex items-center justify-between max-w-screen-xl mx-auto">
                <div class="flex items-center gap-2 text-yellow-800 text-sm">
                    <i class="fas fa-exclamation-triangle text-yellow-500"></i>
                    <span>
                        Your <strong>{{ $sub->plan->name }}</strong> subscription expires in
                        <strong>{{ $sub->daysRemaining() }} day{{ $sub->daysRemaining() !== 1 ? 's' : '' }}</strong>.
                    </span>
                </div>
                <a href="{{ route('subscription.renew') }}"
                   class="text-xs font-semibold text-yellow-800 underline hover:text-yellow-900 whitespace-nowrap ml-4">
                    Renew Now →
                </a>
            </div>
        </div>
    @endif

    @if($sub && $sub->isInGrace() && !$user->hasRole('super-admin'))
        <div class="bg-red-50 border-b border-red-200 px-4 py-2">
            <div class="flex items-center justify-between max-w-screen-xl mx-auto">
                <div class="flex items-center gap-2 text-red-800 text-sm">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span>
                        Your subscription has expired. You are in a grace period — access ends
                        <strong>{{ $sub->grace_ends_at?->format('M d') }}</strong>.
                    </span>
                </div>
                <a href="{{ route('subscription.renew') }}"
                   class="text-xs font-semibold text-red-800 underline hover:text-red-900 whitespace-nowrap ml-4">
                    Renew Immediately →
                </a>
            </div>
        </div>
    @endif
@endauth
@extends('layouts.main-layout')

@section('title', 'Choose Your Plan')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        {{-- Hero Section --}}
        <div class="text-center mb-10">
            <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white mb-2">
                Choose Your Plan
            </h1>
            <p class="text-lg text-gray-500 dark:text-gray-400 max-w-2xl mx-auto">
                Start with a free trial. No credit card required. Upgrade or downgrade anytime.
            </p>
        </div>

        {{-- Current Plan Banner --}}
        @if ($currentSubscription && $currentPlan)
            <div class="mb-8 rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-800 flex items-center justify-center">
                        <i class="fas fa-check text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">
                            You're on the <span class="text-blue-600 dark:text-blue-400">{{ $currentPlan->name }}</span> plan
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            @if ($currentSubscription->onTrial())
                                Trial ends {{ $currentSubscription->trial_ends_at?->format('M d, Y') }}
                            @elseif ($currentSubscription->cancel_at_period_end && $currentSubscription->accessEndsAt())
                                Ends {{ $currentSubscription->accessEndsAt()->format('M d, Y') }}
                            @else
                                Active subscription
                            @endif
                        </p>
                    </div>
                </div>
                @can('billing.view')
                <a href="{{ route('billing.index') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <i class="fas fa-credit-card"></i>
                    Manage Billing
                </a>
                @endcan
            </div>
        @endif

        {{-- Free trial: one per company and per owner email (PO-15) --}}
        @if ($trialAvailable && ! $currentSubscription)
            @can('billing.manage')
                <div class="mb-8 rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        Try every feature free for {{ settings('trial_days', 14) }} days. No credit card required.
                    </p>
                    <form method="POST" action="{{ route('billing.trial') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium">
                            Start Free Trial
                        </button>
                    </form>
                </div>
            @endcan
        @endif

        {{-- Plans Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-7xl mx-auto">
            @foreach ($plans as $plan)
                @php
                    $isCurrentPlan = $currentPlan && $currentPlan->id === $plan->id;
                    $monthly = $plan->currentPrice('month');
                @endphp
                <div class="relative flex flex-col rounded-2xl border-2 border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-all duration-200 bg-white dark:bg-gray-800 overflow-hidden">
                    <div class="p-6 sm:p-8 flex flex-col flex-grow">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-1">{{ $plan->name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ $plan->description }}</p>

                        {{-- Price --}}
                        @if ($monthly)
                            <div class="mb-6">
                                <span class="text-3xl font-bold text-gray-900 dark:text-white">${{ number_format((float) $monthly->amount, 2) }}</span>
                                <span class="text-gray-500 dark:text-gray-400">/month</span>
                            </div>
                        @endif

                        {{-- Features --}}
                        <ul class="space-y-3 mb-8 flex-grow">
                            @foreach ($plan->marketing_bullets ?? [] as $bullet)
                                <li class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                                    <i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0"></i>
                                    <span>{{ $bullet }}</span>
                                </li>
                            @endforeach
                        </ul>

                        {{-- CTA: prices come from plan_prices; the client only picks one --}}
                        @if ($isCurrentPlan)
                            <button disabled
                                class="w-full py-3 px-4 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 font-medium cursor-not-allowed">
                                Current Plan
                            </button>
                        @else
                            @can('billing.manage')
                                <div class="space-y-2">
                                    @foreach ($plan->prices as $price)
                                        <form method="POST" action="{{ route('checkout') }}">
                                            @csrf
                                            <input type="hidden" name="plan_price_id" value="{{ $price->id }}">
                                            <button type="submit" @disabled(! $price->stripe_price_id)
                                                class="w-full py-3 px-4 rounded-xl text-center font-medium transition-all bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed">
                                                {{ $price->interval === 'year' ? 'Yearly' : 'Monthly' }} · ${{ number_format((float) $price->amount, 2) }}
                                            </button>
                                        </form>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-center text-gray-500 dark:text-gray-400">Ask your company owner to choose a plan.</p>
                            @endcan
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Trust Badges --}}
        <div class="mt-12 flex flex-wrap justify-center gap-8 text-sm text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-2">
                <i class="fas fa-lock text-green-500"></i>
                Secure payment via Stripe
            </span>
            <span class="flex items-center gap-2">
                <i class="fas fa-sync-alt text-green-500"></i>
                Cancel anytime
            </span>
            <span class="flex items-center gap-2">
                <i class="fas fa-headset text-green-500"></i>
                Support included
            </span>
        </div>
    </div>
@endsection

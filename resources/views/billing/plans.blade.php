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
                            @elseif ($currentSubscription->ends_at)
                                {{ $currentSubscription->ends_at->diffForHumans() }} remaining
                            @else
                                Active subscription
                            @endif
                        </p>
                    </div>
                </div>
                <a href="{{ route('billing.index') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <i class="fas fa-credit-card"></i>
                    Manage Billing
                </a>
            </div>
        @endif

        {{-- Plans Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 max-w-7xl mx-auto">
            @foreach ($plans as $plan)
                @php
                    $isCurrentPlan = $currentPlan && $currentPlan->id === $plan->id;
                @endphp
                <div class="relative flex flex-col rounded-2xl border-2 transition-all duration-200 {{ $plan->is_featured ? 'border-indigo-500 dark:border-indigo-400 shadow-lg shadow-indigo-500/10 scale-[1.02]' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' }} bg-white dark:bg-gray-800 overflow-hidden">
                    {{-- Featured Badge --}}
                    @if ($plan->is_featured)
                        <div class="absolute top-0 left-0 right-0 py-1.5 bg-indigo-600 text-white text-center text-xs font-semibold uppercase tracking-wider">
                            Most Popular
                        </div>
                    @endif

                    <div class="p-6 sm:p-8 flex flex-col flex-grow {{ $plan->is_featured ? 'pt-10' : '' }}">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-1">{{ $plan->name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ $plan->description }}</p>

                        {{-- Price --}}
                        <div class="mb-6">
                            @if ($plan->price == 0)
                                <span class="text-3xl font-bold text-green-600 dark:text-green-400">Free</span>
                                <span class="text-gray-500 dark:text-gray-400">/trial</span>
                            @else
                                <span class="text-3xl font-bold text-gray-900 dark:text-white">${{ number_format($plan->price, 2) }}</span>
                                <span class="text-gray-500 dark:text-gray-400">/{{ $plan->billing_cycle }}</span>
                            @endif
                        </div>

                        {{-- Features --}}
                        <ul class="space-y-3 mb-8 flex-grow">
                            @foreach ($plan->features ?? [] as $feature)
                                <li class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-300">
                                    <i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0"></i>
                                    <span>{{ $feature }}</span>
                                </li>
                            @endforeach
                        </ul>

                        {{-- CTA --}}
                        @auth
                            @if ($isCurrentPlan)
                                <button disabled
                                    class="w-full py-3 px-4 rounded-xl bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 font-medium cursor-not-allowed">
                                    Current Plan
                                </button>
                            @else
                                <a href="{{ route('checkout', $plan->slug) }}"
                                    class="block w-full py-3 px-4 rounded-xl text-center font-medium transition-all {{ $plan->is_featured ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-md hover:shadow-lg' : 'bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 hover:bg-gray-800 dark:hover:bg-gray-200' }}">
                                    @if ($plan->price == 0)
                                        Start Free Trial
                                    @else
                                        Get Started
                                    @endif
                                </a>
                            @endif
                        @else
                            <a href="{{ route('login') }}"
                                class="block w-full py-3 px-4 rounded-xl text-center font-medium border-2 border-indigo-600 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors">
                                Log in to Subscribe
                            </a>
                        @endauth
                    </div>

                    @if ($plan->billing_cycle === 'trial')
                        <div class="px-6 py-3 bg-gray-50 dark:bg-gray-900/50 text-center text-sm text-gray-500 dark:text-gray-400">
                            No credit card required
                        </div>
                    @elseif($plan->billing_cycle === 'yearly')
                        <div class="px-6 py-3 bg-green-50 dark:bg-green-900/20 text-center text-sm text-green-600 dark:text-green-400 font-medium">
                            Save ~17% vs monthly
                        </div>
                    @endif
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

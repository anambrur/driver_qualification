@extends('layouts.main-layout')

@section('title', 'Billing & Subscription')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                Billing & Subscription
            </h1>
            <p class="mt-1 text-gray-500 dark:text-gray-400">
                Manage your subscription, payment method, and payment history.
            </p>
        </div>

        @if (session('success'))
            <div class="mb-6 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 p-4 flex items-center gap-3">
                <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                <p class="text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        @endif
        @if (session('error'))
            <div class="mb-6 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-4 flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-red-600 dark:text-red-400 text-xl"></i>
                <p class="text-red-800 dark:text-red-200">{{ session('error') }}</p>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-6">
                @if ($subscription && $plan)
                    @php
                        $isAccessible = $subscription->isAccessible();
                        $onTrial = $subscription->onTrial();
                        $onGrace = $subscription->onGracePeriod();
                    @endphp
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <div class="flex items-center justify-between">
                                <h2 class="font-semibold text-gray-900 dark:text-white">Current Plan</h2>
                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                    {{ $onTrial ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : '' }}
                                    {{ $onGrace ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : '' }}
                                    {{ $isAccessible && !$onTrial && !$onGrace ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : '' }}
                                    {{ !$isAccessible ? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' : '' }}">
                                    @if ($onTrial)
                                        Trial
                                    @elseif ($onGrace)
                                        Cancelling
                                    @elseif ($isAccessible)
                                        Active
                                    @else
                                        {{ ucfirst($subscription->stripe_status) }}
                                    @endif
                                </span>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $plan->name }}</h3>
                                    <p class="text-gray-500 dark:text-gray-400 mt-1">{{ $plan->description }}</p>
                                    <div class="mt-4 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                                        <p>Billing: <strong class="capitalize">{{ $subscription->billing_cycle }}</strong>
                                            @if ((float) $subscription->amount > 0)
                                                — ${{ number_format($subscription->amount, 2) }} {{ $subscription->currency }}
                                            @endif
                                        </p>
                                        @if ($onTrial)
                                            <p>Trial ends <strong>{{ $subscription->trial_ends_at?->format('M d, Y') }}</strong></p>
                                        @endif
                                        @if ($subscription->accessEndsAt())
                                            <p>
                                                @if ($onGrace || $subscription->billing_cycle === 'trial')
                                                    Access until
                                                @else
                                                    Current period ends
                                                @endif
                                                <strong>{{ $subscription->accessEndsAt()->format('M d, Y') }}</strong>
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    @if ($subscription->stripe_subscription_id || auth()->user()->stripe_id)
                                        <a href="{{ route('billing.portal') }}"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-colors">
                                            <i class="fas fa-external-link-alt"></i>
                                            Manage in Stripe
                                        </a>
                                    @endif
                                    @if ($onGrace && $subscription->stripe_subscription_id)
                                        <form action="{{ route('billing.resume') }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition-colors">
                                                <i class="fas fa-play"></i>
                                                Resume Subscription
                                            </button>
                                        </form>
                                    @elseif ($isAccessible && !$onTrial)
                                        <form action="{{ route('billing.cancel') }}" method="POST" class="inline"
                                            onsubmit="return confirm('Are you sure? Your subscription will remain active until the end of the billing period.');">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-red-300 dark:border-red-700 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-medium transition-colors">
                                                <i class="fas fa-times"></i>
                                                Cancel Plan
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/30 p-8 text-center">
                        <div class="w-16 h-16 mx-auto rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mb-4">
                            <i class="fas fa-credit-card text-2xl text-amber-600 dark:text-amber-400"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No Active Subscription</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-sm mx-auto">
                            Subscribe to a plan to access all features. Start with a free trial—no credit card required.
                        </p>
                        <a href="{{ route('pricing.plans') }}"
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition-colors">
                            <i class="fas fa-rocket"></i>
                            View Plans
                        </a>
                    </div>
                @endif

                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="font-semibold text-gray-900 dark:text-white">Payment History</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Payments recorded from Stripe</p>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($payments as $payment)
                            <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ $payment->paid_at?->format('M d, Y') ?? $payment->created_at->format('M d, Y') }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        ${{ number_format($payment->amount, 2) }} {{ $payment->currency }}
                                        · <span class="capitalize">{{ $payment->status }}</span>
                                        @if ($payment->plan)
                                            · {{ $payment->plan->name }}
                                        @endif
                                    </p>
                                </div>
                                @if ($payment->hosted_invoice_url)
                                    <a href="{{ $payment->hosted_invoice_url }}" target="_blank" rel="noopener"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        <i class="fas fa-external-link-alt text-xs"></i>
                                        Invoice
                                    </a>
                                @endif
                            </div>
                        @empty
                            <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-file-invoice text-3xl mb-2 opacity-50"></i>
                                <p>No payments yet</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="{{ route('pricing.plans') }}"
                            class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                                <i class="fas fa-layer-group text-indigo-600 dark:text-indigo-400"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Change Plan</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Upgrade or renew</p>
                            </div>
                        </a>
                        @if (auth()->user()->stripe_id)
                            <a href="{{ route('billing.portal') }}"
                                class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                    <i class="fas fa-credit-card text-green-600 dark:text-green-400"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">Update Payment</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Manage card & billing</p>
                                </div>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

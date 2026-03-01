@extends('layouts.main-layout')

@section('title', 'Billing & Subscription')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        {{-- Page Header --}}
        <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                Billing & Subscription
            </h1>
            <p class="mt-1 text-gray-500 dark:text-gray-400">
                Manage your subscription, payment method, and invoices.
            </p>
        </div>

        {{-- Flash Messages --}}
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
            {{-- Main Content --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Current Plan Card --}}
                @if ($subscription && $plan)
                    <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                            <div class="flex items-center justify-between">
                                <h2 class="font-semibold text-gray-900 dark:text-white">Current Plan</h2>
                                <span class="px-3 py-1 rounded-full text-xs font-medium
                                    {{ $subscription->onTrial() ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : '' }}
                                    {{ $subscription->onGracePeriod() ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : '' }}
                                    {{ $subscription->active() && !$subscription->onTrial() && !$subscription->onGracePeriod() ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : '' }}">
                                    @if ($subscription->onTrial())
                                        Trial
                                    @elseif ($subscription->onGracePeriod())
                                        Cancelling
                                    @else
                                        Active
                                    @endif
                                </span>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                <div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $plan->name }}</h3>
                                    <p class="text-gray-500 dark:text-gray-400 mt-1">{{ $plan->description }}</p>
                                    <div class="mt-4 space-y-1 text-sm">
                                        @if ($subscription->onTrial())
                                            <p>Trial ends <strong>{{ $subscription->trial_ends_at?->format('M d, Y') }}</strong></p>
                                        @endif
                                        @if ($subscription->ends_at)
                                            <p>@if ($subscription->onGracePeriod()) Access until @else Next billing @endif
                                                <strong>{{ $subscription->ends_at->format('M d, Y') }}</strong>
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-3">
                                    <a href="{{ route('billing.portal') }}"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-colors">
                                        <i class="fas fa-external-link-alt"></i>
                                        Manage in Stripe
                                    </a>
                                    @if ($subscription->onGracePeriod())
                                        <form action="{{ route('billing.resume') }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition-colors">
                                                <i class="fas fa-play"></i>
                                                Resume Subscription
                                            </button>
                                        </form>
                                    @elseif ($subscription->active() && !$subscription->onTrial())
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
                    {{-- No Subscription --}}
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

                {{-- Invoices --}}
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="font-semibold text-gray-900 dark:text-white">Invoice History</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Download past invoices</p>
                    </div>
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($invoices as $invoice)
                            <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ $invoice->date()->format('M d, Y') }}
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $invoice->total() }}
                                    </p>
                                </div>
                                <a href="{{ route('billing.invoice.download', $invoice->id) }}"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <i class="fas fa-download text-xs"></i>
                                    Download
                                </a>
                            </div>
                        @empty
                            <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <i class="fas fa-file-invoice text-3xl mb-2 opacity-50"></i>
                                <p>No invoices yet</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Quick Actions --}}
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="{{ route('pricing.plans') }}"
                            class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center group-hover:bg-indigo-200 dark:group-hover:bg-indigo-900/50">
                                <i class="fas fa-layer-group text-indigo-600 dark:text-indigo-400"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Change Plan</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Upgrade or downgrade</p>
                            </div>
                        </a>
                        <a href="{{ route('billing.portal') }}"
                            class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors group">
                            <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center group-hover:bg-green-200 dark:group-hover:bg-green-900/50">
                                <i class="fas fa-credit-card text-green-600 dark:text-green-400"></i>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">Update Payment</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Manage card & billing</p>
                            </div>
                        </a>
                    </div>
                </div>

                {{-- Help --}}
                <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">Need Help?</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Manage your subscription, update your payment method, or download invoices through our secure Stripe portal.
                    </p>
                    <a href="{{ route('billing.portal') }}"
                        class="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
                        Open Billing Portal
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

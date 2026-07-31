@extends('layouts.main-layout')

@section('title', 'Payment Successful')

@section('content')
    <div class="min-h-[60vh] flex items-center justify-center p-4">
        <div class="max-w-lg w-full text-center">
            <div class="mx-auto w-20 h-20 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-6">
                <i class="fas fa-check text-4xl text-green-600 dark:text-green-400"></i>
            </div>

            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">
                @if (($subscription?->billing_cycle ?? null) === 'trial' || ($plan?->billing_cycle ?? null) === 'trial')
                    Trial Activated!
                @else
                    You're all set!
                @endif
            </h1>

            <p class="text-gray-600 dark:text-gray-400 mb-1">
                @if ($plan)
                    You're now on the <strong class="text-gray-900 dark:text-white">{{ $plan->name }}</strong> plan.
                @else
                    Your subscription has been activated. If you just paid, it may take a moment to sync.
                @endif
            </p>

            @if ($subscription && (float) $subscription->amount > 0)
                <p class="text-lg font-semibold text-gray-900 dark:text-white mt-3">
                    ${{ number_format($subscription->amount, 2) }}
                    <span class="text-sm font-normal text-gray-500 capitalize">/ {{ $subscription->billing_cycle }}</span>
                </p>
            @elseif (($plan?->billing_cycle ?? null) === 'trial')
                <p class="text-sm text-gray-500 mt-3">No credit card was required. Enjoy your trial!</p>
            @endif

            @if (!empty($latestPayment) && $latestPayment->status === 'paid')
                <div class="mt-6 rounded-xl border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-800 p-4 text-left">
                    <p class="text-sm font-medium text-green-800 dark:text-green-200">Payment recorded</p>
                    <p class="text-sm text-green-700 dark:text-green-300 mt-1">
                        ${{ number_format($latestPayment->amount, 2) }} {{ $latestPayment->currency }}
                        · {{ $latestPayment->paid_at?->format('M d, Y') ?? 'Just now' }}
                    </p>
                    @if ($latestPayment->hosted_invoice_url)
                        <a href="{{ $latestPayment->hosted_invoice_url }}" target="_blank" rel="noopener"
                           class="inline-block mt-2 text-sm text-green-700 underline">View Stripe invoice</a>
                    @endif
                </div>
            @endif

            <div class="flex flex-col sm:flex-row gap-3 justify-center mt-8">
                <a href="{{ route('admin.dashboard') }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium transition-colors">
                    <i class="fas fa-th-large"></i>
                    Go to Dashboard
                </a>
                <a href="{{ route('billing.index') }}"
                    class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl border-2 border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 font-medium transition-colors">
                    <i class="fas fa-credit-card"></i>
                    Manage Billing
                </a>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.main-layout')

@section('title', 'Payment Successful')

@section('content')
    <div class="min-h-[60vh] flex items-center justify-center p-4">
        <div class="max-w-md w-full text-center">
            {{-- Success Icon --}}
            <div class="mx-auto w-20 h-20 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center mb-6">
                <i class="fas fa-check text-4xl text-green-600 dark:text-green-400"></i>
            </div>

            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">
                Payment Successful!
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mb-1">
                @if ($plan)
                    You're now subscribed to the <strong class="text-gray-900 dark:text-white">{{ $plan->name }}</strong> plan.
                @else
                    Your subscription has been activated successfully.
                @endif
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-500 mb-8">
                A confirmation email has been sent to your inbox.
            </p>

            {{-- Actions --}}
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
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

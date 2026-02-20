{{-- resources/views/subscription/expired.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'Subscription Expired')

@section('content')
    <div class="container mx-auto px-4 py-5">
        <div class="flex justify-center">
            <div class="w-full md:w-2/3 lg:w-1/2 text-center">

                <div class="mb-4">
                    <i class="bi bi-clock-history text-red-500 text-6xl"></i>
                </div>

                <h1 class="font-bold text-3xl mb-3">Your Subscription Has Expired</h1>

                @if ($subscription)
                    <p class="text-xl text-gray-600 mb-2">
                        Your <strong>{{ $subscription->plan->name }}</strong> plan expired on
                        <strong>{{ $subscription->ends_at?->format('F d, Y') ?? 'N/A' }}</strong>.
                    </p>
                @endif

                <p class="text-gray-500 mb-5">
                    Renew your subscription to restore full access to all features.
                </p>

                {{-- Quick Renew Button --}}
                @if ($subscription)
                    <a href="{{ route('subscription.renew') }}"
                        class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg mr-3 mb-3 transition-colors">
                        <i class="bi bi-arrow-clockwise mr-2"></i> Renew {{ $subscription->plan->name }}
                    </a>
                @endif

                <a href="{{ route('admin.plans.index') }}"
                    class="inline-block bg-white hover:bg-gray-50 text-gray-700 font-bold py-3 px-6 rounded-lg border border-gray-300 mb-3 transition-colors">
                    View All Plans
                </a>

                {{-- Plan highlights for upsell --}}
                @if ($plans->count())
                    <hr class="my-5 border-gray-200">
                    <h4 class="font-semibold text-xl mb-4">Choose a Plan to Continue</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-left">
                        @foreach ($plans->take(3) as $plan)
                            <div class="col-span-1">
                                <div class="bg-white rounded-lg border border-gray-200 shadow-sm h-full">
                                    <div class="p-3">
                                        <h6 class="font-bold">{{ $plan->name }}</h6>
                                        <div class="text-xl font-bold text-blue-600 mb-2">
                                            @if ($plan->price == 0)
                                                Free
                                            @else
                                                ${{ number_format($plan->price, 2) }}<small
                                                    class="text-sm text-gray-500">/{{ $plan->billing_cycle }}</small>
                                            @endif
                                        </div>
                                        <a href="{{ route('subscription.checkout', $plan) }}"
                                            class="inline-block w-full px-4 py-2 text-sm text-center text-blue-600 hover:text-white border border-blue-600 hover:bg-blue-600 rounded transition-colors">Select</a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-5">
                    <p class="text-gray-500 text-sm">
                        Need help? <a href="mailto:{{ config('mail.from.address') }}"
                            class="text-blue-600 hover:underline">Contact Support</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

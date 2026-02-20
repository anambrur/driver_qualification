{{-- resources/views/subscription/plans.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'Choose Your Plan')

@section('content')
    <div class="container mx-auto px-4 py-5">
        <div class="text-center mb-5">
            <h1 class="text-4xl font-bold">Choose Your Plan</h1>
            <p class="text-xl text-gray-500">Start with a free trial. No credit card required.</p>
        </div>

        @if ($currentSubscription)
            <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-4 flex items-center">
                <i class="bi bi-info-circle-fill mr-2"></i>
                <div>
                    You are currently on the <strong>{{ $currentSubscription->plan->name }}</strong> plan.
                    @if ($currentSubscription->daysRemaining() !== null)
                        <strong>{{ $currentSubscription->daysRemaining() }} days</strong> remaining.
                    @else
                        Lifetime access.
                    @endif
                    <a href="{{ route('subscription.my') }}" class="font-bold underline ml-2">Manage subscription →</a>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 justify-center">
            @foreach ($plans as $plan)
                <div class="col-span-1">
                    <div
                        class="bg-white rounded-lg border border-gray-200 shadow-sm h-full flex flex-col relative {{ $plan->is_featured ? 'border-2 border-blue-500' : '' }}">
                        @if ($plan->is_featured)
                            <div class="bg-blue-600 text-white text-center py-2 rounded-t-lg">
                                <small class="font-semibold uppercase tracking-wider">Most Popular</small>
                            </div>
                        @endif

                        <div class="p-4 flex flex-col flex-grow">
                            <h4 class="font-bold text-xl">{{ $plan->name }}</h4>
                            <p class="text-gray-500 text-sm mb-3">{{ $plan->description }}</p>

                            {{-- Price --}}
                            <div class="mb-4">
                                @if ($plan->price == 0)
                                    <span class="text-3xl font-bold text-green-600">Free</span>
                                @else
                                    <span class="text-3xl font-bold">${{ number_format($plan->price, 2) }}</span>
                                    <span class="text-gray-500">/{{ $plan->billing_cycle }}</span>
                                @endif
                            </div>

                            {{-- Features --}}
                            <ul class="list-none mb-4 flex-grow">
                                @foreach ($plan->features ?? [] as $feature)
                                    <li class="mb-2 flex items-start">
                                        <i class="bi bi-check-circle-fill text-green-500 mr-2 mt-1"></i>
                                        <span class="text-sm">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            {{-- CTA --}}
                            @auth
                                @if ($currentSubscription && $currentSubscription->plan_id === $plan->id)
                                    <button class="w-full px-4 py-2 bg-gray-100 text-gray-500 rounded-lg cursor-not-allowed"
                                        disabled>Current Plan</button>
                                @else
                                    <a href="{{ route('subscription.checkout', $plan) }}"
                                        class="w-full text-center px-4 py-2 rounded-lg transition-colors {{ $plan->is_featured ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-white border border-blue-600 text-blue-600 hover:bg-blue-50' }}">
                                        @if ($plan->price == 0)
                                            Start Free Trial
                                        @else
                                            Get Started
                                        @endif
                                    </a>
                                @endif
                            @else
                                <a href="{{ route('login') }}"
                                    class="w-full text-center px-4 py-2 bg-white border border-blue-600 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">Log
                                    in to Subscribe</a>
                            @endauth
                        </div>

                        @if ($plan->billing_cycle === 'trial')
                            <div
                                class="border-t border-gray-100 text-center text-gray-500 text-sm py-2 bg-gray-50 rounded-b-lg">
                                No credit card required
                            </div>
                        @elseif($plan->billing_cycle === 'yearly')
                            <div
                                class="border-t border-gray-100 text-center text-green-600 text-sm py-2 bg-gray-50 rounded-b-lg">
                                Save ~17% vs monthly
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection

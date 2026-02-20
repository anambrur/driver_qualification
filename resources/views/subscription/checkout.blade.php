{{-- resources/views/subscription/checkout.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'Checkout – ' . $plan->name)

@section('content')
    <div class="container mx-auto px-4 py-5">
        <div class="flex justify-center">
            <div class="w-full md:w-2/3 lg:w-1/2 xl:w-2/5">

                <a href="{{ route('subscription.plans') }}"
                    class="text-gray-500 hover:text-gray-700 no-underline inline-flex items-center mb-4">
                    <i class="bi bi-arrow-left mr-2"></i> Back to Plans
                </a>

                {{-- Plan Summary --}}
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm mb-4 overflow-hidden">
                    <div class="bg-blue-600 text-white py-3 px-4">
                        <div class="flex justify-between items-center">
                            <div>
                                <h5 class="mb-0 font-bold">{{ $plan->name }}</h5>
                                <small class="text-blue-100">{{ ucfirst($plan->billing_cycle) }} billing</small>
                            </div>
                            <div class="text-right">
                                <span class="text-2xl font-bold mb-0">
                                    @if ($plan->price == 0)
                                        Free
                                    @else
                                        ${{ number_format($plan->price, 2) }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <p class="text-gray-500 text-sm mb-2">{{ $plan->description }}</p>
                        <ul class="list-none mb-0">
                            @foreach ($plan->features ?? [] as $feature)
                                <li class="text-sm mb-1">
                                    <i class="bi bi-check-circle-fill text-green-500 mr-1"></i>{{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                {{-- Payment --}}
                @if ($plan->price == 0)
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm text-center">
                        <div class="p-4 py-5">
                            <i class="bi bi-gift-fill text-green-500 text-4xl mb-3 inline-block"></i>
                            <h5 class="font-bold">Free Trial – No Card Needed</h5>
                            <form method="POST" action="{{ route('subscription.purchase', $plan) }}">
                                @csrf
                                <input type="hidden" name="payment_method" value="manual">
                                <button
                                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 w-full mt-2 rounded-lg transition-colors">Activate
                                    Free Trial</button>
                            </form>
                        </div>
                    </div>
                @else
                    {{-- Stripe --}}
                    @if ($stripeEnabled)
                        <div class="bg-white rounded-lg border border-gray-200 shadow-sm mb-3">
                            <div class="p-4">
                                <div class="flex items-center mb-3">
                                    <span class="font-semibold mr-2">Pay with Card</span>
                                    <span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded border">Powered by
                                        Stripe</span>
                                </div>
                                <div class="flex justify-between bg-gray-50 rounded-lg p-3 mb-3">
                                    <span class="text-gray-500">Total due today</span>
                                    <strong>${{ number_format($plan->price, 2) }} {{ $plan->currency }}</strong>
                                </div>
                                <form method="POST" action="{{ route('subscription.stripe.checkout', $plan) }}">
                                    @csrf
                                    <button type="submit"
                                        class="w-full py-3 font-semibold text-white rounded-lg transition-colors hover:opacity-90"
                                        style="background:#635bff">
                                        <i class="bi bi-lock-fill mr-2"></i>
                                        Pay ${{ number_format($plan->price, 2) }} Securely
                                    </button>
                                </form>
                                <p class="text-center text-gray-500 text-xs mt-2 mb-0">
                                    <i class="bi bi-shield-check mr-1"></i> SSL encrypted · PCI compliant
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Manual / Bank Transfer --}}
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                        <div class="p-4">
                            <p class="font-semibold mb-2"><i class="bi bi-bank mr-2"></i>Pay via Bank Transfer</p>
                            <p class="text-gray-500 text-sm">Your access will be activated after manual verification (1–2
                                business days).</p>
                            <form method="POST" action="{{ route('subscription.purchase', $plan) }}">
                                @csrf
                                <input type="hidden" name="payment_method" value="bank_transfer">
                                <button
                                    class="w-full px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium rounded-lg transition-colors">Request
                                    Bank Transfer</button>
                            </form>
                        </div>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mt-3">
                        {{ $errors->first() }}</div>
                @endif

            </div>
        </div>
    </div>
@endsection

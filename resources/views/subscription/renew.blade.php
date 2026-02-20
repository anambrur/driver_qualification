{{-- resources/views/subscription/renew.blade.php --}}
@extends('layouts.main-layout')

@section('title', 'Renew Subscription')

@section('content')
    <div class="container mx-auto px-4 py-5">
        <div class="flex justify-center">
            <div class="w-full lg:w-1/2">

                <div class="mb-4">
                    <a href="{{ route('subscription.my') }}" class="text-gray-500 hover:text-gray-700 no-underline">← My
                        Subscription</a>
                </div>

                <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                    <div class="border-b border-gray-200 bg-gray-50 px-4 py-3 font-semibold">Renew —
                        {{ $subscription->plan->name }}</div>
                    <div class="p-4">

                        <div class="flex justify-between mb-4 p-3 bg-gray-50 rounded-lg">
                            <div>
                                <div class="font-semibold">{{ $subscription->plan->name }}</div>
                                <small class="text-gray-500">{{ ucfirst($subscription->plan->billing_cycle) }} ·
                                    {{ $subscription->plan->duration_days }} days</small>
                            </div>
                            <div class="font-bold text-blue-600 text-lg">
                                ${{ number_format($subscription->plan->price, 2) }}
                            </div>
                        </div>

                        @if (session('error'))
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                                {{ session('error') }}</div>
                        @endif

                        <form id="renew-form" action="{{ route('subscription.renew.process') }}" method="POST">
                            @csrf
                            <input type="hidden" name="payment_intent_id" id="payment_intent_id">

                            @if ($clientSecret)
                                <div class="mb-3">
                                    <label class="block text-sm font-semibold mb-2">Card Information</label>
                                    <div id="card-element" class="w-full px-3 py-3 border border-gray-300 rounded-lg"></div>
                                    <div id="card-errors" class="text-red-600 text-sm mt-1"></div>
                                </div>

                                <div class="flex items-center gap-2 mb-4 text-gray-500 text-sm">
                                    <i class="bi bi-lock-fill text-green-600"></i>
                                    Secured by Stripe.
                                </div>

                                <button id="submit-btn" type="submit"
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors disabled:opacity-50">
                                    <span id="btn-text">Pay & Renew
                                        ${{ number_format($subscription->plan->price, 2) }}</span>
                                    <span id="btn-spinner"
                                        class="hidden ml-2 inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                                </button>
                            @else
                                <button type="submit"
                                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">Renew
                                    Free Plan</button>
                            @endif
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@if ($clientSecret)
    @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            const stripe = Stripe('{{ $stripeKey }}');
            const elements = stripe.elements();
            const clientSecret = '{{ $clientSecret }}';

            const cardElement = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#32325d',
                        '::placeholder': {
                            color: '#a0aec0'
                        }
                    }
                }
            });
            cardElement.mount('#card-element');
            cardElement.on('change', ({
                error
            }) => {
                document.getElementById('card-errors').textContent = error ? error.message : '';
            });

            const form = document.getElementById('renew-form');
            const btn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const spinner = document.getElementById('btn-spinner');

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                btn.disabled = true;
                btnText.textContent = 'Processing...';
                spinner.classList.remove('hidden');

                const {
                    paymentIntent,
                    error
                } = await stripe.confirmCardPayment(clientSecret, {
                    payment_method: {
                        card: cardElement
                    }
                });

                if (error) {
                    document.getElementById('card-errors').textContent = error.message;
                    btn.disabled = false;
                    btnText.textContent = 'Pay & Renew ${{ number_format($subscription->plan->price, 2) }}';
                    spinner.classList.add('hidden');
                    return;
                }

                if (paymentIntent.status === 'succeeded') {
                    document.getElementById('payment_intent_id').value = paymentIntent.id;
                    form.submit();
                }
            });
        </script>
    @endpush
@endif

@extends('layouts.main-layout')

@section('title', 'Choose Your Plan')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <div class="mx-auto mb-8 max-w-2xl text-center">
            <h1 class="text-3xl font-semibold tracking-tight text-foreground">Choose Your Plan</h1>
            <p class="mt-2 text-muted-foreground">
                Start with a free trial. No credit card required. Upgrade or downgrade anytime.
            </p>
        </div>

        <div class="mx-auto max-w-7xl space-y-6">
            @if ($currentSubscription && $currentPlan)
                <x-ui.alert tone="info" icon="circle-check" :title="'You\'re on the '.$currentPlan->name.' plan'">
                    <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p>
                            @if ($currentSubscription->onTrial())
                                Trial ends {{ $currentSubscription->trial_ends_at?->format('M d, Y') }}
                            @elseif ($currentSubscription->cancel_at_period_end && $currentSubscription->accessEndsAt())
                                Ends {{ $currentSubscription->accessEndsAt()->format('M d, Y') }}
                            @else
                                Active subscription
                            @endif
                        </p>
                        @can('billing.view')
                            <x-ui.button :href="route('billing.index')" variant="outline" size="sm" icon="credit-card">Manage Billing</x-ui.button>
                        @endcan
                    </div>
                </x-ui.alert>
            @endif

            {{-- Free trial: one per company and per owner email (PO-15) --}}
            @if ($trialAvailable && ! $currentSubscription)
                @can('billing.manage')
                    <x-ui.alert tone="success" icon="rocket" title="Free trial">
                        <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p>Try every feature free for {{ settings('trial_days', 14) }} days. No credit card required.</p>
                            <form method="POST" action="{{ route('billing.trial') }}">
                                @csrf
                                <x-ui.button type="submit" size="sm">Start Free Trial</x-ui.button>
                            </form>
                        </div>
                    </x-ui.alert>
                @endcan
            @endif

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($plans as $plan)
                    @php
                        $isCurrentPlan = $currentPlan && $currentPlan->id === $plan->id;
                        $monthly = $plan->currentPrice('month');
                    @endphp
                    <x-ui.card :class="$isCurrentPlan ? '*:last:flex *:last:grow *:last:flex-col ring-2 ring-primary' : '*:last:flex *:last:grow *:last:flex-col'">
                        <div class="flex grow flex-col">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-xl font-semibold text-foreground">{{ $plan->name }}</h3>
                                @if ($isCurrentPlan)
                                    <x-ui.badge tone="brand">Current</x-ui.badge>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">{{ $plan->description }}</p>

                            @if ($monthly)
                                <p class="mt-6">
                                    <span class="text-3xl font-semibold text-foreground">${{ number_format((float) $monthly->amount, 2) }}</span>
                                    <span class="text-muted-foreground">/month</span>
                                </p>
                            @endif

                            <ul class="mt-6 mb-8 grow space-y-3">
                                @foreach ($plan->marketing_bullets ?? [] as $bullet)
                                    <li class="flex items-start gap-3 text-sm text-muted-foreground">
                                        <x-ui.icon name="circle-check" class="mt-0.5 size-4 shrink-0 text-success" />
                                        <span>{{ $bullet }}</span>
                                    </li>
                                @endforeach
                            </ul>

                            {{-- CTA: prices come from plan_prices; the client only picks one --}}
                            @if ($isCurrentPlan)
                                <x-ui.button variant="secondary" class="w-full" disabled>Current Plan</x-ui.button>
                            @else
                                @can('billing.manage')
                                    <div class="space-y-2">
                                        @foreach ($plan->prices as $price)
                                            <form method="POST" action="{{ route('checkout') }}">
                                                @csrf
                                                <input type="hidden" name="plan_price_id" value="{{ $price->id }}">
                                                <x-ui.button type="submit" :variant="$loop->first ? null : 'outline'" class="w-full" :disabled="! $price->stripe_price_id">
                                                    {{ $price->interval === 'year' ? 'Yearly' : 'Monthly' }} · ${{ number_format((float) $price->amount, 2) }}
                                                </x-ui.button>
                                            </form>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-center text-sm text-muted-foreground">Ask your company owner to choose a plan.</p>
                                @endcan
                            @endif
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <div class="flex flex-wrap justify-center gap-x-8 gap-y-3 pt-4 text-sm text-muted-foreground">
                <span class="flex items-center gap-2">
                    <x-ui.icon name="lock" class="size-4 text-success" />
                    Secure payment via Stripe
                </span>
                <span class="flex items-center gap-2">
                    <x-ui.icon name="refresh-cw" class="size-4 text-success" />
                    Cancel anytime
                </span>
                <span class="flex items-center gap-2">
                    <x-ui.icon name="headset" class="size-4 text-success" />
                    Support included
                </span>
            </div>
        </div>
    </div>
@endsection

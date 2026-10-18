@extends('layouts.main-layout')

@section('title', 'Billing & Subscription')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Billing & Subscription" description="Manage your subscription, payment method, and payment history." />

        <x-ui.flash />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                @if ($subscription && $plan)
                    @php
                        $isAccessible = $subscription->isAccessible();
                        $onTrial = $subscription->onTrial();
                        $onGrace = $subscription->canResume();
                        $pastDue = $accessState === \App\Services\Billing\AccessState::Grace;
                        $tone = match (true) {
                            $onTrial => 'info',
                            $onGrace => 'warning',
                            $isAccessible => 'success',
                            default => 'neutral',
                        };
                    @endphp
                    <x-ui.card title="Current Plan">
                        <x-slot:action>
                            <x-ui.badge :tone="$tone">
                                @if ($onTrial)
                                    Trial
                                @elseif ($pastDue)
                                    Payment failed
                                @elseif ($accessState === \App\Services\Billing\AccessState::ReadOnly)
                                    Read-only
                                @elseif ($onGrace)
                                    Cancelling
                                @elseif ($isAccessible)
                                    Active
                                @else
                                    {{ ucfirst(str_replace('_', ' ', $subscription->status)) }}
                                @endif
                            </x-ui.badge>
                        </x-slot:action>

                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <h3 class="text-xl font-semibold text-foreground">{{ $plan->name }}</h3>
                                <p class="mt-1 text-sm text-muted-foreground">{{ $plan->description }}</p>
                                <div class="mt-4 space-y-1 text-sm text-muted-foreground">
                                    <p>Billing: <strong class="text-foreground">{{ $subscription->source === 'stripe' ? ($subscription->interval === 'year' ? 'Yearly' : 'Monthly') : ucfirst($subscription->source) }}</strong>
                                        @if ((float) $subscription->amount > 0)
                                            — ${{ number_format($subscription->amount, 2) }} {{ $subscription->currency }}
                                        @endif
                                    </p>
                                    @if ($onTrial)
                                        <p>Trial ends <strong class="text-foreground">{{ $subscription->trial_ends_at?->format('M d, Y') }}</strong></p>
                                    @endif
                                    @if ($subscription->accessEndsAt())
                                        <p>
                                            @if ($onGrace || $subscription->source !== 'stripe')
                                                Access until
                                            @else
                                                Current period ends
                                            @endif
                                            <strong class="text-foreground">{{ $subscription->accessEndsAt()->format('M d, Y') }}</strong>
                                        </p>
                                    @endif
                                </div>
                            </div>
                            @can('billing.manage')
                                <div class="flex flex-wrap gap-2 sm:shrink-0 *:max-sm:flex-1">
                                    @if ($company->stripe_customer_id)
                                        <form action="{{ route('billing.portal') }}" method="POST">
                                            @csrf
                                            <x-ui.button type="submit" icon="external-link" class="w-full">Manage in Stripe</x-ui.button>
                                        </form>
                                    @endif
                                    @if ($onGrace && $subscription->stripe_subscription_id)
                                        <form action="{{ route('billing.resume') }}" method="POST">
                                            @csrf
                                            <x-ui.button type="submit" variant="outline" icon="play" class="w-full">Resume Subscription</x-ui.button>
                                        </form>
                                    @elseif ($isAccessible && ! $onTrial && ! $subscription->cancel_at_period_end)
                                        <form action="{{ route('billing.cancel') }}" method="POST"
                                            data-confirm="Cancel your plan?"
                                            data-confirm-text="Your subscription will remain active until the end of the billing period."
                                            data-confirm-button="Cancel Plan" data-confirm-cancel="Keep Plan">
                                            @csrf
                                            <x-ui.button type="submit" variant="destructive" icon="x" class="w-full">Cancel Plan</x-ui.button>
                                        </form>
                                    @endif
                                </div>
                            @endcan
                        </div>
                    </x-ui.card>
                @else
                    <x-ui.empty-state icon="credit-card" title="No Active Subscription"
                        description="Subscribe to a plan to access all features. Start with a free trial—no credit card required.">
                        <x-ui.button :href="route('pricing.plans')" icon="rocket">View Plans</x-ui.button>
                    </x-ui.empty-state>
                @endif

                <x-ui.card title="Payment History" description="Payments recorded from Stripe" flush class="pb-0!">
                    <ul class="divide-y divide-border border-t border-border">
                        @forelse ($payments as $payment)
                            <li class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-medium text-foreground">
                                        {{ $payment->paid_at?->format('M d, Y') ?? $payment->created_at->format('M d, Y') }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        ${{ number_format($payment->amount, 2) }} {{ $payment->currency }}
                                        · <span class="capitalize">{{ $payment->status }}</span>
                                        @if ($payment->plan)
                                            · {{ $payment->plan->name }}
                                        @endif
                                    </p>
                                </div>
                                @if ($payment->hosted_invoice_url)
                                    <x-ui.button :href="$payment->hosted_invoice_url" target="_blank" rel="noopener" variant="outline" size="sm" icon="external-link">Invoice</x-ui.button>
                                @endif
                            </li>
                        @empty
                            <li class="flex flex-col items-center gap-2 px-6 py-8 text-center text-sm text-muted-foreground">
                                <x-ui.icon name="file-text" class="size-8 opacity-50" />
                                No payments yet
                            </li>
                        @endforelse
                    </ul>
                </x-ui.card>
            </div>

            <div>
                <x-ui.card title="Quick Actions">
                    <div class="space-y-3">
                        <a href="{{ route('pricing.plans') }}"
                            class="flex items-center gap-3 rounded-lg border border-border p-3 transition-colors hover:bg-accent">
                            <span class="flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                                <x-ui.icon name="layers" class="size-5" />
                            </span>
                            <span>
                                <span class="block font-medium text-foreground">Change Plan</span>
                                <span class="block text-xs text-muted-foreground">Upgrade or renew</span>
                            </span>
                        </a>
                        @if ($company->stripe_customer_id && auth()->user()->can('billing.manage'))
                            <form action="{{ route('billing.portal') }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="flex w-full items-center gap-3 rounded-lg border border-border p-3 text-left transition-colors hover:bg-accent">
                                    <span class="flex size-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                                        <x-ui.icon name="credit-card" class="size-5" />
                                    </span>
                                    <span>
                                        <span class="block font-medium text-foreground">Update Payment</span>
                                        <span class="block text-xs text-muted-foreground">Manage card & billing</span>
                                    </span>
                                </button>
                            </form>
                        @endif
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>
@endsection

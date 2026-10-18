@extends('layouts.main-layout')

@section('title', 'Payment Successful')

@section('content')
    <div class="flex min-h-[60vh] items-center justify-center p-4">
        <x-ui.card class="w-full max-w-lg text-center">
            <span class="mx-auto mb-6 flex size-16 items-center justify-center rounded-full bg-success/10 text-success">
                <x-ui.icon name="check" class="size-8" />
            </span>

            <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                @if ($subscription?->source === 'trial')
                    Trial Activated!
                @else
                    You're all set!
                @endif
            </h1>

            <p class="mt-2 text-muted-foreground">
                @if ($plan)
                    You're now on the <strong class="text-foreground">{{ $plan->name }}</strong> plan.
                @else
                    Your subscription has been activated. If you just paid, it may take a moment to sync.
                @endif
            </p>

            @if ($subscription && (float) $subscription->amount > 0)
                <p class="mt-3 text-lg font-semibold text-foreground">
                    ${{ number_format($subscription->amount, 2) }}
                    <span class="text-sm font-normal text-muted-foreground">/ {{ $subscription->interval }}</span>
                </p>
            @elseif ($subscription?->source === 'trial')
                <p class="mt-3 text-sm text-muted-foreground">No credit card was required. Enjoy your trial!</p>
            @endif

            @if (! empty($latestPayment) && $latestPayment->status === 'paid')
                <x-ui.alert tone="success" title="Payment recorded" class="mt-6 text-left">
                    <p>
                        ${{ number_format($latestPayment->amount, 2) }} {{ $latestPayment->currency }}
                        · {{ $latestPayment->paid_at?->format('M d, Y') ?? 'Just now' }}
                    </p>
                    @if ($latestPayment->hosted_invoice_url)
                        <a href="{{ $latestPayment->hosted_invoice_url }}" target="_blank" rel="noopener" class="mt-1 inline-block underline">View Stripe invoice</a>
                    @endif
                </x-ui.alert>
            @endif

            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                <x-ui.button :href="route('admin.dashboard')" icon="layout-grid">Go to Dashboard</x-ui.button>
                <x-ui.button :href="route('billing.index')" variant="outline" icon="credit-card">Manage Billing</x-ui.button>
            </div>
        </x-ui.card>
    </div>
@endsection

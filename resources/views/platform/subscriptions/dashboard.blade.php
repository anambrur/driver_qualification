@extends('layouts.platform')

@section('title', 'Subscriptions')

@section('content')
    <x-ui.page-header title="Subscriptions" description="Revenue from paid invoices and the open subscriptions behind it.">
        <x-slot:actions>
            <x-ui.button variant="outline" icon="list" :href="route('platform.subscriptions.index')">All subscriptions</x-ui.button>
            @can('platform.payments.view')
                <x-ui.button variant="outline" icon="receipt" :href="route('platform.subscriptions.payments')">Payments</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Revenue, all time" :value="'$'.number_format($revenueTotal, 2)" icon="wallet" tone="success" />
        <x-ui.stat label="Revenue this month" :value="'$'.number_format($revenueMonth, 2)" icon="trending-up" tone="brand" :hint="now()->format('F Y')" />
        <x-ui.stat label="Ending in 7 days" :value="number_format($endingSoon)" icon="calendar-clock" :tone="$endingSoon ? 'warning' : 'neutral'" />
    </div>

    <div class="mb-6 grid gap-6 md:grid-cols-2">
        @foreach (['By status' => [$byStatus, true], 'By source' => [$bySource, false]] as $title => [$counts, $badges])
            <x-ui.card :title="$title" description="Open subscriptions only.">
                @if ($counts->isEmpty())
                    <p class="text-sm text-muted-foreground">None.</p>
                @else
                    <dl class="divide-y divide-border">
                        @foreach ($counts as $key => $total)
                            <div class="flex items-center justify-between py-2">
                                <dt>
                                    @if ($badges)
                                        @include('partials.ui.status-badge', ['status' => $key])
                                    @else
                                        {{ ucfirst(str_replace('_', ' ', $key)) }}
                                    @endif
                                </dt>
                                <dd class="font-medium tabular-nums">{{ number_format($total) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    <x-ui.card title="Recent" description="The 10 newest subscriptions, open or ended.">
        <x-slot:action>
            <x-ui.button variant="link" size="sm" :href="route('platform.subscriptions.index')">View all</x-ui.button>
        </x-slot:action>
        @include('platform.subscriptions.table', ['subscriptions' => $recent])
    </x-ui.card>
@endsection

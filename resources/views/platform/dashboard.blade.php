@extends('layouts.platform')

@section('title', 'Dashboard')

@php
    $canCompanies = auth('platform')->user()?->can('platform.companies.view');
    $canSubscriptions = auth('platform')->user()?->can('platform.subscriptions.view');
@endphp

@section('content')
    <x-ui.page-header title="Platform overview" description="Companies and subscriptions across the platform. No tenant records are read here." />

    <section aria-labelledby="companies-heading" class="mb-8">
        <h2 id="companies-heading" class="mb-3 text-sm font-medium text-muted-foreground">Companies</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach (['active' => ['building-2', 'success'], 'suspended' => ['pause', 'warning'], 'deactivated' => ['circle-off', 'neutral']] as $status => [$icon, $tone])
                <x-ui.stat :label="ucfirst($status)" :value="number_format($companies[$status] ?? 0)" :icon="$icon" :tone="$tone"
                    :href="$canCompanies ? route('platform.companies.index') : null" />
            @endforeach
        </div>
    </section>

    <section aria-labelledby="subscriptions-heading">
        <h2 id="subscriptions-heading" class="mb-3 text-sm font-medium text-muted-foreground">Open subscriptions</h2>
        @if ($subscriptions->isEmpty())
            <x-ui.empty-state icon="credit-card" title="No open subscriptions yet" />
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($subscriptions as $status => $total)
                    <x-ui.stat :label="\App\Support\Ui\Status::label($status)" :value="number_format($total)" icon="credit-card"
                        :tone="\App\Support\Ui\Status::tone($status)" :href="$canSubscriptions ? route('platform.subscriptions.index') : null" />
                @endforeach
            </div>
        @endif
    </section>
@endsection

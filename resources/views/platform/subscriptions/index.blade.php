@extends('layouts.platform')

@section('title', 'All subscriptions')

@php
    $statuses = collect(\App\DataTables\Platform\SubscriptionTable::STATUSES)->mapWithKeys(fn ($status) => [$status => \App\Support\Ui\Status::label($status)])->all();
@endphp

@section('content')
    <x-ui.page-header title="All subscriptions" description="Every subscription, open and ended, across all companies.">
        <x-slot:actions>
            <x-ui.button variant="outline" icon="layout-dashboard" :href="route('platform.subscriptions.dashboard')">Overview</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.datatable id="subscriptions" title="Subscriptions" description="Search by company name.">
        <x-slot:toolbar>
            <div class="flex flex-wrap items-center gap-2">
                <x-ui.select data-dt-filter="status" aria-label="Filter by status" :options="['' => 'Any status'] + $statuses" class="w-auto!" />
                <x-ui.select data-dt-filter="plan_id" aria-label="Filter by plan" :options="['' => 'Any plan'] + $plans->pluck('name', 'id')->all()" class="w-auto!" />
                <x-ui.select data-dt-filter="open" aria-label="Open or ended" :options="['' => 'Open and ended', '1' => 'Open only']" class="w-auto!" />
            </div>
        </x-slot:toolbar>

        <th>Company</th>
        <th>Plan</th>
        <th>Status</th>
        <th>Source</th>
        <th>Amount</th>
        <th>Started</th>
        <th>Ends</th>

        <x-slot:empty>
            <x-ui.empty-state icon="credit-card" title="No subscriptions yet" class="border-0" />
        </x-slot:empty>
    </x-ui.datatable>
@endsection

@push('scripts')
    <script type="module">
        window.dqTable('#subscriptions', {
            ajax: @json(route('platform.subscriptions.index')),
            primary: 0,
            order: [[5, 'desc']],
            columns: [
                { data: 'company', orderable: false, searchable: false },
                { data: 'plan', orderable: false, searchable: false },
                { data: 'status', orderable: false, searchable: false },
                { data: 'source', orderable: false, searchable: false },
                { data: 'amount', name: 'amount', searchable: false },
                { data: 'created_at_formatted', name: 'created_at_formatted', searchable: false },
                { data: 'ends_at_formatted', name: 'ends_at_formatted', searchable: false },
            ],
        });
    </script>
@endpush

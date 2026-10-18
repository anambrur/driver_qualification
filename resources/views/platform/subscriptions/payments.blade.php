@extends('layouts.platform')

@section('title', 'Payments')

@php
    $statuses = collect(\App\DataTables\Platform\PaymentTable::STATUSES)->mapWithKeys(fn ($status) => [$status => ucfirst($status)])->all();
@endphp

@section('content')
    <x-ui.page-header title="Payments" description="Invoices and charges recorded from Stripe, newest first." />

    <x-ui.datatable id="payments" title="All payments" description="Search by company name.">
        <x-slot:toolbar>
            <x-ui.select data-dt-filter="status" aria-label="Filter by status" :options="['' => 'Any status'] + $statuses" class="w-auto!" />
        </x-slot:toolbar>

        <th>Paid</th>
        <th>Company</th>
        <th>Plan</th>
        <th>Amount</th>
        <th>Status</th>
        <th>Reason</th>

        <x-slot:empty>
            <x-ui.empty-state icon="receipt" title="No payments yet" class="border-0" />
        </x-slot:empty>
    </x-ui.datatable>
@endsection

@push('scripts')
    <script type="module">
        window.dqTable('#payments', {
            ajax: @json(route('platform.subscriptions.payments')),
            primary: 1,
            order: [[0, 'desc']],
            columns: [
                { data: 'paid_at_formatted', name: 'paid_at_formatted', searchable: false },
                { data: 'company', orderable: false, searchable: false },
                { data: 'plan', orderable: false, searchable: false },
                { data: 'amount', name: 'amount', searchable: false },
                { data: 'status', orderable: false, searchable: false },
                { data: 'billing_reason', orderable: false, searchable: false },
            ],
        });
    </script>
@endpush

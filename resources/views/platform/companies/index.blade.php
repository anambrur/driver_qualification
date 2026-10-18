@extends('layouts.platform')

@section('title', 'Companies')

@php
    $statuses = collect(\App\Services\Platform\PlatformCompanyService::STATUSES)->mapWithKeys(fn ($status) => [$status => ucfirst($status)])->all();
@endphp

@section('content')
    <x-ui.page-header title="Companies" description="Every customer company, its owner and its status. Tenant records are never shown here.">
        @can('platform.companies.manage')
            <x-slot:actions>
                <x-ui.button icon="plus" :href="route('platform.companies.create')">New company</x-ui.button>
            </x-slot:actions>
        @endcan
    </x-ui.page-header>

    <x-ui.datatable id="companies" title="All companies" description="Search by company name, contact email or owner email.">
        <x-slot:toolbar>
            <x-ui.select data-dt-filter="status" aria-label="Filter by status" :options="['' => 'Any status'] + $statuses" class="w-auto!" />
        </x-slot:toolbar>

        <th>Company</th>
        <th>Owner</th>
        <th>Status</th>
        <th>Created</th>

        <x-slot:empty>
            <x-ui.empty-state icon="building-2" title="No companies yet" class="border-0" />
        </x-slot:empty>
    </x-ui.datatable>
@endsection

@push('scripts')
    <script type="module">
        window.dqTable('#companies', {
            ajax: @json(route('platform.companies.index')),
            primary: 0,
            order: [[0, 'asc']],
            columns: [
                { data: 'company', name: 'company' },
                { data: 'owner', orderable: false, searchable: false },
                { data: 'status', orderable: false, searchable: false },
                { data: 'created_at_formatted', name: 'created_at_formatted', searchable: false },
            ],
        });
    </script>
@endpush

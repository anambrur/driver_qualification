@extends('layouts.platform')

@section('title', 'Asset transfers')

@section('content')
    <x-ui.page-header title="Asset transfers" description="Vehicles and trailers moved between companies. Both owners are notified of every transfer.">
        <x-slot:actions>
            <x-ui.button icon="arrow-right-left" :href="route('platform.asset-transfers.create')">New transfer</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.datatable id="asset-transfers" title="Transfer history" description="Search by source or target company name.">
        <x-slot:toolbar>
            <x-ui.select data-dt-filter="asset_type" aria-label="Filter by asset type" :options="['' => 'All assets', 'vehicle' => 'Vehicles', 'trailer' => 'Trailers']" class="w-auto!" />
        </x-slot:toolbar>

        <th class="w-12">#</th>
        <th>When</th>
        <th>Asset</th>
        <th>From → to</th>
        <th>Copied</th>
        <th>Status</th>
        <th>By</th>
        <th>Reason</th>

        <x-slot:empty>
            <x-ui.empty-state icon="arrow-right-left" title="No transfers yet" class="border-0">
                <x-ui.button icon="arrow-right-left" size="sm" :href="route('platform.asset-transfers.create')">New transfer</x-ui.button>
            </x-ui.empty-state>
        </x-slot:empty>
    </x-ui.datatable>
@endsection

@push('scripts')
    <script type="module">
        window.dqTable('#asset-transfers', {
            ajax: @json(route('platform.asset-transfers.index')),
            primary: 3,
            order: [[0, 'desc']],
            columns: [
                { data: 'id', name: 'id', searchable: false },
                { data: 'performed_at_formatted', name: 'performed_at_formatted', searchable: false },
                { data: 'asset_type', orderable: false, searchable: false },
                { data: 'route', orderable: false, searchable: false },
                { data: 'copied', orderable: false, searchable: false },
                { data: 'status', orderable: false, searchable: false },
                { data: 'performed_by', orderable: false, searchable: false },
                { data: 'reason', orderable: false, searchable: false },
            ],
        });
    </script>
@endpush

@extends('layouts.main-layout')

@section('title', 'Shippers')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Shippers" description="Saved shippers for your trips. Inactive shippers aren't offered on new trips but stay on the trips that use them.">
            @can('shippers.manage')
                <x-slot:actions>
                    <x-ui.button icon="plus" :href="route('shippers.create')">Add shipper</x-ui.button>
                </x-slot:actions>
            @endcan
        </x-ui.page-header>

        <x-ui.datatable id="shipper-table" title="All shippers">
            <x-slot:toolbar>
                <x-ui.select data-dt-filter="status" aria-label="Filter by status" :options="['' => 'Active and inactive', 'active' => 'Active', 'inactive' => 'Inactive']" class="w-auto!" />
            </x-slot:toolbar>

            <th>Shipper</th>
            <th>City, state</th>
            <th>Contact</th>
            <th>Status</th>
            <th class="text-right"><span class="sr-only">Actions</span></th>

            <x-slot:empty>
                <x-ui.empty-state icon="warehouse" title="No shippers yet" description="Save a shipper once and pick it on later trips." class="border-0" />
            </x-slot:empty>
        </x-ui.datatable>
    </div>
@endsection

@push('scripts')
    <script type="module">
        window.dqTable('#shipper-table', {
            ajax: @json(route('shippers.data')),
            order: [[0, 'asc']],
            columns: [
                { data: 'shipper', name: 'shipper', searchable: false },
                { data: 'place', name: 'place', searchable: false },
                { data: 'contact', orderable: false, searchable: false },
                { data: 'status', orderable: false, searchable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-right' },
            ],
        });
    </script>
@endpush

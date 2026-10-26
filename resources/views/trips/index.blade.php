@extends('layouts.main-layout')

@section('title', 'Trips')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Trips" description="Every trip of your company, newest trip date first.">
            @can('trips.create')
                <x-slot:actions>
                    <x-ui.button icon="plus" :href="route('trips.create')">New trip</x-ui.button>
                </x-slot:actions>
            @endcan
        </x-ui.page-header>

        <x-ui.datatable id="trip-table" title="All trips">
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.select data-dt-filter="status" aria-label="Filter by status" :options="['' => 'Any status'] + collect($statuses)->mapWithKeys(fn ($status) => [$status => \App\Support\Ui\Status::label($status)])->all()" class="w-auto!" />
                    <x-ui.input type="date" data-dt-filter="date_from" aria-label="Trip date from" class="w-auto!" />
                    <x-ui.input type="date" data-dt-filter="date_to" aria-label="Trip date to" class="w-auto!" />
                    <x-ui.button variant="ghost" size="sm" data-dt-reset>Clear</x-ui.button>
                </div>
            </x-slot:toolbar>

            <th>Trip</th>
            <th>Date</th>
            <th>Status</th>
            <th>Shipper</th>
            <th>Start → drop-off</th>
            <th>Driver</th>
            <th>Vehicle</th>

            <x-slot:empty>
                <x-ui.empty-state icon="route" title="No trips yet" description="Create a trip, add its start and drop-off, then confirm its number." class="border-0" />
            </x-slot:empty>
        </x-ui.datatable>
    </div>
@endsection

@push('scripts')
    <script type="module">
        window.dqTable('#trip-table', {
            ajax: @json(route('trips.data')),
            order: [[1, 'desc']],
            columns: [
                { data: 'number', name: 'number', searchable: false },
                { data: 'date', name: 'date', searchable: false },
                { data: 'status', orderable: false, searchable: false },
                { data: 'shipper', orderable: false, searchable: false },
                { data: 'route', orderable: false, searchable: false },
                { data: 'driver', orderable: false, searchable: false },
                { data: 'vehicle', orderable: false, searchable: false },
            ],
        });
    </script>
@endpush

@extends('layouts.main-layout')

@section('title', 'Exports')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Exports" description="ZIP files of a driver's DQ file or of the whole company. Each file can be downloaded for 7 days." />

        <x-ui.datatable id="export-table" title="All exports">
            <th>Type</th>
            <th>Scope</th>
            <th>Requested</th>
            <th>Status</th>
            <th>Size</th>
            <th>Expires</th>
            <th class="text-right"><span class="sr-only">Actions</span></th>

            <x-slot:empty>
                <x-ui.empty-state icon="archive" title="No exports yet" description="Use “Download ZIP” on a driver page or on the Documents page." class="border-0" />
            </x-slot:empty>
        </x-ui.datatable>
    </div>
@endsection

@push('scripts')
    <script type="module">
        const table = window.dqTable('#export-table', {
            ajax: @json(route('exports.data')),
            order: [[2, 'desc']],
            columns: [
                { data: 'type_label', name: 'type', orderable: false, searchable: false, className: 'font-medium text-foreground' },
                { data: 'scope', orderable: false, searchable: false },
                { data: 'requested', name: 'requested', searchable: false },
                { data: 'status_badge', orderable: false, searchable: false },
                { data: 'size', orderable: false, searchable: false },
                { data: 'expires', orderable: false, searchable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-right' },
            ],
        });

        // Queued and running exports finish in the background
        setInterval(() => {
            if (document.visibilityState === 'visible') table.ajax.reload(null, false);
        }, 15000);
    </script>
@endpush

@extends('layouts.main-layout')

@section('title', 'Drivers')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Drivers" description="Manage and monitor your drivers.">
            <x-slot:actions>
                @can('drivers.create')
                    <x-ui.button :href="route('admin.driver.create')" icon="plus">Add driver</x-ui.button>
                @endcan
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.datatable id="drivers" title="All drivers">
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2" role="group" aria-label="Filter by status">
                    @foreach (['all' => 'All', 'pending' => 'Pending', 'active' => 'Active', 'inactive' => 'Inactive', 'rejected' => 'Rejected'] as $status => $label)
                        <button type="button" class="btn" data-variant="outline" data-size="sm" data-dt-filter="status" data-value="{{ $status }}"
                            aria-pressed="{{ $status === 'all' ? 'true' : 'false' }}">
                            {{ $label }}
                            <span class="badge" data-tone="{{ $status === 'all' ? 'neutral' : \App\Support\Ui\Status::tone($status) }}">{{ $statusCounts[$status] ?? 0 }}</span>
                        </button>
                    @endforeach
                </div>
            </x-slot:toolbar>

            <th class="w-14"><span class="sr-only">Photo</span></th>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>State</th>
            <th>License exp.</th>
            <th>Medical exp.</th>
            <th>Hire date</th>
            <th class="text-right"><span class="sr-only">Actions</span></th>

            <x-slot:empty>
                <x-ui.empty-state icon="id-card" title="No drivers yet" description="Add your first driver or share your application link." class="border-0">
                    @can('drivers.create')
                        <x-ui.button :href="route('admin.driver.create')" icon="plus" size="sm">Add driver</x-ui.button>
                    @endcan
                </x-ui.empty-state>
            </x-slot:empty>
        </x-ui.datatable>
    </div>
@endsection

@push('scripts')
    <script type="module">
        const table = window.dqTable('#drivers', {
            ajax: @json(route('admin.driver.index')),
            primary: 1,
            order: [[1, 'asc']],
            columns: [
                { data: 'photo', orderable: false, searchable: false },
                { data: 'full_name', name: 'first_name', className: 'font-medium text-foreground' },
                { data: 'email', name: 'email' },
                { data: 'status', name: 'status' },
                { data: 'state', name: 'state_code' },
                { data: 'license_expiration_date', orderable: false, searchable: false },
                { data: 'medical_certificate_expiration_date', searchable: false },
                { data: 'hired_at', searchable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-right' },
            ],
        });

        // Delete buttons carry the id and name in data-* attributes (never inline JS).
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action="delete-driver"]');
            if (button) deleteDriver(button.dataset.driverId, button.dataset.driverName);
        });

        function deleteDriver(id, name) {
            showAppConfirm(`${name} will be removed. This cannot be undone.`, {
                title: 'Delete driver?', icon: 'warning', confirmButtonText: 'Delete', variant: 'destructive',
            }).then((result) => {
                if (!result.isConfirmed) return;
                fetch(`/admin/driver/${encodeURIComponent(id)}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                }).then((response) => {
                    if (!response.ok) throw new Error(response.statusText);
                    window.notify('success', 'Driver deleted');
                    table.ajax.reload(null, false);
                }).catch((error) => window.notify('error', `Request failed: ${error.message}`));
            });
        }
    </script>
@endpush

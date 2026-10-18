@extends('layouts.main-layout')

@section('title', 'Asset Groups')

@php
    $unitOptions = fn ($rows) => $rows->mapWithKeys(fn ($row) => [$row->id => trim($row->unit_no.' · '.$row->year.' '.$row->make.' '.$row->model)])->all();
    $unitDetails = fn ($rows) => $rows->mapWithKeys(fn ($row) => [$row->id => ['unit_no' => $row->unit_no, 'vin' => $row->vin]])->all();
@endphp

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Asset groups" description="Pair a driver with a vehicle and, optionally, a trailer.">
            @can('asset-groups.create')
                <x-slot:actions>
                    <x-ui.button icon="plus" data-action="create">Add asset group</x-ui.button>
                </x-slot:actions>
            @endcan
        </x-ui.page-header>

        <x-ui.datatable id="asset-groups" title="All asset groups">
            <x-slot:toolbar>
                <x-ui.select data-dt-filter="status" aria-label="Filter by status" :options="['' => 'All live groups', 'active' => 'Active', 'inactive' => 'Inactive', 'deleted' => 'Deleted']" class="w-auto!" />
            </x-slot:toolbar>

            <th class="w-12">#</th>
            <th>Group</th>
            <th>Drivers</th>
            <th>Assets</th>
            <th>Status</th>
            <th>Added</th>
            <th class="text-right"><span class="sr-only">Actions</span></th>

            <x-slot:empty>
                <x-ui.empty-state icon="users-round" title="No asset groups yet" description="Group a driver with the vehicle and trailer they run." class="border-0">
                    @can('asset-groups.create')
                        <x-ui.button icon="plus" size="sm" data-action="create">Add asset group</x-ui.button>
                    @endcan
                </x-ui.empty-state>
            </x-slot:empty>
        </x-ui.datatable>
    </div>

    @canany(['asset-groups.create', 'asset-groups.edit'])
        <x-ui.dialog id="asset-group-dialog" title="Add asset group" :close-on-overlay="false" class="w-full sm:max-w-2xl">
            <form id="asset-group-form" class="grid gap-4 sm:grid-cols-2" novalidate>
                <x-ui.field label="Vehicle" name="vehicle_id" required>
                    <x-ui.select name="vehicle_id" :options="$unitOptions($vehicles)" placeholder="Select vehicle" required />
                    <p data-unit-detail="vehicle" class="text-sm text-muted-foreground" hidden></p>
                </x-ui.field>
                <x-ui.field label="Trailer" name="trailer_id" hint="Optional.">
                    <x-ui.select name="trailer_id" :options="$unitOptions($trailers)" placeholder="No trailer" />
                    <p data-unit-detail="trailer" class="text-sm text-muted-foreground" hidden></p>
                </x-ui.field>
                <x-ui.field label="Group name" name="group_name" required hint="Filled in from the vehicle (GR#unit) until you change it.">
                    <x-ui.input name="group_name" required maxlength="100" autocomplete="off" />
                </x-ui.field>
                <x-ui.field label="Status" name="status" required>
                    <x-ui.select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" required />
                </x-ui.field>
                <x-ui.field label="Primary driver" name="driver_id" required class="sm:col-span-2" hint="Active drivers.">
                    <x-ui.select name="driver_id" :options="$drivers->mapWithKeys(fn ($d) => [$d->id => trim($d->first_name.' '.$d->last_name)])->all()" placeholder="Select driver" required />
                    <p data-driver-contact class="text-sm text-muted-foreground" hidden></p>
                </x-ui.field>
            </form>
            <x-slot:footer>
                <button type="button" class="btn" data-variant="outline" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit" form="asset-group-form" class="btn">Save asset group</button>
            </x-slot:footer>
        </x-ui.dialog>
    @endcanany
@endsection

@push('scripts')
    <script type="module">
        const units = { vehicle: @json($unitDetails($vehicles)), trailer: @json($unitDetails($trailers)) };
        const driverDetailsUrl = @json(route('admin.drivers.get-driver-details', ':id'));
        const canViewDrivers = @json(auth()->user()->can('drivers.view'));

        const table = window.dqTable('#asset-groups', {
            ajax: @json(route('admin.asset-group.index')),
            primary: 1,
            order: [[5, 'desc']],
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'group_info', name: 'group_info' },
                { data: 'drivers_info', orderable: false, searchable: false },
                { data: 'assets_info', orderable: false, searchable: false },
                { data: 'status', orderable: false, searchable: false },
                { data: 'created_at_formatted', name: 'created_at_formatted', searchable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-right' },
            ],
        });

        const form = document.getElementById('asset-group-form');
        if (form) {
            const { vehicle_id: vehicle, trailer_id: trailer, group_name: groupName, driver_id: driver } = form.elements;
            const contact = form.querySelector('[data-driver-contact]');
            let autoName = '';

            const showUnit = (kind, select) => {
                const detail = form.querySelector(`[data-unit-detail="${kind}"]`);
                const unit = units[kind][select.value];
                detail.hidden = !unit;
                detail.textContent = unit ? `VIN ${unit.vin}` : '';
            };

            const nameFromVehicle = () => {
                const unit = units.vehicle[vehicle.value];
                const next = unit ? `GR#${unit.unit_no}` : '';
                // Only replace a name the form filled in, never one the user typed
                if (groupName.value === '' || groupName.value === autoName) groupName.value = next;
                autoName = next;
            };

            const showContact = async () => {
                contact.hidden = true;
                if (!driver.value || !canViewDrivers) return;
                const { ok, json } = await window.dqCrud.request(window.dqCrud.at(driverDetailsUrl, driver.value));
                if (!ok) return;
                const parts = [json.data.main_phone || json.data.alt_phone, json.data.email].filter(Boolean);
                contact.textContent = parts.join(' · ');
                contact.hidden = parts.length === 0;
            };

            vehicle.addEventListener('change', () => { showUnit('vehicle', vehicle); nameFromVehicle(); });
            trailer.addEventListener('change', () => showUnit('trailer', trailer));
            driver.addEventListener('change', showContact);

            window.dqCrud.page({
                table,
                dialog: '#asset-group-dialog',
                singular: 'asset group',
                defaults: { status: 'active' },
                routes: {
                    store: @json(route('admin.asset-group.store')),
                    edit: @json(route('admin.asset-group.edit', ':id')),
                    update: @json(route('admin.asset-group.update', ':id')),
                    destroy: @json(route('admin.asset-group.destroy', ':id')),
                    restore: @json(route('admin.asset-group.restore', ':id')),
                },
                afterFill: (form, record) => {
                    // The select lists active drivers only; keep the group's driver selectable if it is not
                    driver.querySelectorAll('[data-inactive]').forEach((option) => option.remove());
                    if (record.driver && ![...driver.options].some((option) => option.value === String(record.driver.id))) {
                        const option = new Option(`${record.driver.first_name} ${record.driver.last_name} (inactive)`, record.driver.id);
                        option.dataset.inactive = '';
                        driver.add(option);
                        driver.value = record.driver.id;
                    }
                    autoName = vehicle.value && units.vehicle[vehicle.value] ? `GR#${units.vehicle[vehicle.value].unit_no}` : '';
                    showUnit('vehicle', vehicle);
                    showUnit('trailer', trailer);
                    showContact();
                },
            });
        }
    </script>
@endpush

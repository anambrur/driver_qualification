@extends('layouts.main-layout')

@section('title', 'Vehicles')

@php
    $options = fn ($rows) => $rows->pluck('name', 'id')->all();
@endphp

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Vehicles" description="Your power units, their specifications and odometer.">
            @can('vehicles.create')
                <x-slot:actions>
                    <x-ui.button icon="plus" data-action="create">Add vehicle</x-ui.button>
                </x-slot:actions>
            @endcan
        </x-ui.page-header>

        <x-ui.datatable id="vehicles" title="All vehicles">
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.select data-dt-filter="vehicle_type_id" aria-label="Filter by type" :options="['' => 'All types'] + $options($vehicleTypes)" class="w-auto!" />
                    <x-ui.select data-dt-filter="status" aria-label="Filter by status" :options="['' => 'All, with deleted', 'active' => 'Active', 'deleted' => 'Deleted']" class="w-auto!" />
                </div>
            </x-slot:toolbar>

            <th class="w-12">#</th>
            <th>Vehicle</th>
            <th>Type and group</th>
            <th>Fuel and odometer</th>
            <th>Status</th>
            <th>Added</th>
            <th class="text-right"><span class="sr-only">Actions</span></th>

            <x-slot:empty>
                <x-ui.empty-state icon="truck" title="No vehicles yet" description="Add your first power unit to track its documents and maintenance." class="border-0">
                    @can('vehicles.create')
                        <x-ui.button icon="plus" size="sm" data-action="create">Add vehicle</x-ui.button>
                    @endcan
                </x-ui.empty-state>
            </x-slot:empty>
        </x-ui.datatable>
    </div>

    @canany(['vehicles.create', 'vehicles.edit'])
        <x-ui.dialog id="vehicle-dialog" title="Add vehicle" :close-on-overlay="false" class="w-full sm:max-w-3xl">
            <form id="vehicle-form" class="grid gap-6" novalidate>
                <fieldset>
                    <legend class="float-left mb-3 w-full text-sm font-medium">Basic information</legend>
                    <div class="clear-both grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-ui.field label="Unit number" name="unit_no" required>
                        <x-ui.input name="unit_no" required maxlength="50" placeholder="For example T001" autocomplete="off" />
                    </x-ui.field>
                    <x-ui.field label="VIN" name="vin" required hint="17 characters, no I, O or Q." class="sm:col-span-2">
                        <x-ui.input name="vin" required minlength="17" maxlength="17" autocomplete="off" spellcheck="false" class="font-mono uppercase" />
                    </x-ui.field>
                    <x-ui.field label="Year" name="year" required>
                        <x-ui.input type="number" name="year" required min="1900" max="{{ date('Y') + 1 }}" placeholder="{{ date('Y') }}" />
                    </x-ui.field>
                    <x-ui.field label="Make" name="make" required>
                        <x-ui.input name="make" required maxlength="100" placeholder="For example Peterbilt" />
                    </x-ui.field>
                    <x-ui.field label="Model" name="model" required>
                        <x-ui.input name="model" required maxlength="100" placeholder="For example 389" />
                    </x-ui.field>
                    <x-ui.field label="Vehicle type" name="vehicle_type_id">
                        <x-ui.select name="vehicle_type_id" :options="$options($vehicleTypes)" placeholder="Select type" />
                    </x-ui.field>
                    <x-ui.field label="Vehicle group" name="vehicle_group_id">
                        <x-ui.select name="vehicle_group_id" :options="$options($vehicleGroups)" placeholder="Select group" />
                    </x-ui.field>
                    <x-ui.field label="Owned by" name="owned_by">
                        <x-ui.input name="owned_by" maxlength="100" placeholder="Company name" />
                    </x-ui.field>
                    <x-ui.field label="Color" name="color">
                        <x-ui.input name="color" maxlength="50" placeholder="For example White" />
                    </x-ui.field>
                    </div>
                </fieldset>

                <fieldset class="border-t border-border pt-6">
                    <legend class="float-left mb-3 w-full text-sm font-medium">Details and specifications</legend>
                    <div class="clear-both grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-ui.field label="Odometer (miles)" name="odometer" required>
                        <x-ui.input type="number" name="odometer" required min="0" placeholder="0" />
                    </x-ui.field>
                    <x-ui.field label="Engine hours" name="engine_hours">
                        <x-ui.input type="number" name="engine_hours" min="0" />
                    </x-ui.field>
                    <x-ui.field label="GVW (lbs)" name="gvw">
                        <x-ui.input type="number" name="gvw" min="0" />
                    </x-ui.field>
                    <x-ui.field label="Fuel type" name="fuel_type_id">
                        <x-ui.select name="fuel_type_id" :options="$options($fuelTypes)" placeholder="Select fuel type" />
                    </x-ui.field>
                    <x-ui.field label="Title number" name="title_no">
                        <x-ui.input name="title_no" maxlength="100" />
                    </x-ui.field>
                    <x-ui.field label="Tire size" name="tire_size">
                        <x-ui.input name="tire_size" maxlength="50" placeholder="For example 295/75R22.5" />
                    </x-ui.field>
                    <x-ui.field label="Engine type" name="engine_type">
                        <x-ui.input name="engine_type" maxlength="100" placeholder="For example PACCAR MX-13" />
                    </x-ui.field>
                    <x-ui.field label="Transmission" name="transmission">
                        <x-ui.input name="transmission" maxlength="100" placeholder="For example 10-speed manual" />
                    </x-ui.field>
                    <x-ui.field label="Suspension" name="suspension">
                        <x-ui.input name="suspension" maxlength="100" placeholder="For example Air" />
                    </x-ui.field>
                    <x-ui.field label="Number of axles" name="no_axles">
                        <x-ui.input type="number" name="no_axles" min="1" max="10" />
                    </x-ui.field>
                    <x-ui.field label="Configuration" name="configuration">
                        <x-ui.select name="configuration" :options="['conventional' => 'Conventional', 'cabover' => 'Cabover']" placeholder="Select configuration" />
                    </x-ui.field>
                    <x-ui.field label="Wheel base (inches)" name="wheel_base">
                        <x-ui.input type="number" name="wheel_base" min="0" />
                    </x-ui.field>
                    <x-ui.field label="Size / dimension" name="size_dimension">
                        <x-ui.input name="size_dimension" maxlength="100" placeholder="For example 70 BBC" />
                    </x-ui.field>
                    </div>
                </fieldset>

                <x-ui.field label="Notes" name="notes" class="border-t border-border pt-6">
                    <x-ui.textarea name="notes" rows="3" maxlength="2000" class="w-full" placeholder="Maintenance history, special instructions…" />
                </x-ui.field>
            </form>
            <x-slot:footer>
                <button type="button" class="btn" data-variant="outline" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit" form="vehicle-form" class="btn">Save vehicle</button>
            </x-slot:footer>
        </x-ui.dialog>
    @endcanany
@endsection

@push('scripts')
    <script type="module">
        const table = window.dqTable('#vehicles', {
            ajax: @json(route('admin.vehicle.index')),
            primary: 1,
            order: [[5, 'desc']],
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'vehicle_info', name: 'vehicle_info' },
                { data: 'type_group', orderable: false, searchable: false },
                { data: 'fuel_odometer', orderable: false, searchable: false },
                { data: 'status', orderable: false, searchable: false },
                { data: 'created_at_formatted', name: 'created_at_formatted', searchable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-right' },
            ],
        });

        window.dqCrud.page({
            table,
            dialog: '#vehicle-dialog',
            singular: 'vehicle',
            routes: {
                store: @json(route('admin.vehicle.store')),
                edit: @json(route('admin.vehicle.edit', ':id')),
                update: @json(route('admin.vehicle.update', ':id')),
                destroy: @json(route('admin.vehicle.destroy', ':id')),
                restore: @json(route('admin.vehicle.restore', ':id')),
            },
        });

        document.querySelector('#vehicle-form [name="vin"]')?.addEventListener('input', (event) => {
            event.target.value = event.target.value.toUpperCase();
        });
    </script>
@endpush

@extends('layouts.main-layout')

@section('title', 'Trailers')

@php
    $options = fn ($rows) => $rows->pluck('name', 'id')->all();
@endphp

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Trailers" description="Your trailers and other towed equipment.">
            @can('trailers.create')
                <x-slot:actions>
                    <x-ui.button icon="plus" data-action="create">Add trailer</x-ui.button>
                </x-slot:actions>
            @endcan
        </x-ui.page-header>

        <x-ui.datatable id="trailers" title="All trailers">
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.select data-dt-filter="equipment_type_id" aria-label="Filter by equipment type" :options="['' => 'All equipment types'] + $options($equipmentTypes)" class="w-auto!" />
                    <x-ui.select data-dt-filter="status" aria-label="Filter by status" :options="['' => 'All, with deleted', 'active' => 'Active', 'deleted' => 'Deleted']" class="w-auto!" />
                </div>
            </x-slot:toolbar>

            <th class="w-12">#</th>
            <th>Trailer</th>
            <th>Plate</th>
            <th>Equipment and group</th>
            <th>GVW</th>
            <th>Owned by</th>
            <th>Status</th>
            <th>Added</th>
            <th class="text-right"><span class="sr-only">Actions</span></th>

            <x-slot:empty>
                <x-ui.empty-state icon="container" title="No trailers yet" description="Add your first trailer to track its documents." class="border-0">
                    @can('trailers.create')
                        <x-ui.button icon="plus" size="sm" data-action="create">Add trailer</x-ui.button>
                    @endcan
                </x-ui.empty-state>
            </x-slot:empty>
        </x-ui.datatable>
    </div>

    @canany(['trailers.create', 'trailers.edit'])
        <x-ui.dialog id="trailer-dialog" title="Add trailer" description="Unit details, equipment type and specifications." icon="container" size="xl" :close-on-overlay="false">
            <form id="trailer-form" novalidate>
                <div class="form-section">
                    <h3 class="form-section-title">{{ svg('lucide-tag', '', ['aria-hidden' => 'true']) }} Identification</h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.field label="Unit number" name="unit_no" required>
                            <x-ui.input name="unit_no" required maxlength="50" placeholder="TR001" autocomplete="off" />
                        </x-ui.field>
                        <x-ui.field label="VIN" name="vin" required hint="17 characters, no I, O or Q." class="sm:col-span-2 lg:col-span-3">
                            <x-ui.input name="vin" required minlength="17" maxlength="17" autocomplete="off" spellcheck="false" class="font-mono uppercase tracking-wider" />
                        </x-ui.field>
                        <x-ui.field label="Year" name="year" required>
                            <x-ui.input type="number" name="year" required min="1900" max="{{ date('Y') + 1 }}" placeholder="{{ date('Y') }}" />
                        </x-ui.field>
                        <x-ui.field label="Make" name="make" required>
                            <x-ui.input name="make" required maxlength="100" placeholder="Utility" />
                        </x-ui.field>
                        <x-ui.field label="Model" name="model" required>
                            <x-ui.input name="model" required maxlength="100" placeholder="4000D-X" />
                        </x-ui.field>
                        <x-ui.field label="Color" name="color">
                            <x-ui.input name="color" maxlength="50" placeholder="White" />
                        </x-ui.field>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">{{ svg('lucide-layers', '', ['aria-hidden' => 'true']) }} Classification and specifications</h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <x-ui.field label="Equipment type" name="equipment_type_id" required>
                            <x-ui.select name="equipment_type_id" :options="$options($equipmentTypes)" placeholder="Select equipment type" required />
                        </x-ui.field>
                        <x-ui.field label="Vehicle group" name="vehicle_group_id">
                            <x-ui.select name="vehicle_group_id" :options="$options($vehicleGroups)" placeholder="Select group" />
                        </x-ui.field>
                        <x-ui.field label="Owned by" name="owned_by">
                            <x-ui.input name="owned_by" maxlength="100" placeholder="Company name" />
                        </x-ui.field>
                        <x-ui.field label="GVW (lbs)" name="gvw">
                            <x-ui.input type="number" name="gvw" min="0" />
                        </x-ui.field>
                        <x-ui.field label="Tire size" name="tire_size">
                            <x-ui.input name="tire_size" maxlength="50" placeholder="11R22.5" />
                        </x-ui.field>
                        <x-ui.field label="Title number" name="title_no">
                            <x-ui.input name="title_no" maxlength="100" />
                        </x-ui.field>
                    </div>
                </div>

                @include('admin.vehicle.partials.plate-photo-fields')

                <div class="form-section">
                    <h3 class="form-section-title">{{ svg('lucide-sticky-note', '', ['aria-hidden' => 'true']) }} Notes</h3>
                    <x-ui.field label="Notes" name="notes" class="[&>label]:sr-only">
                        <x-ui.textarea name="notes" rows="3" maxlength="2000" class="w-full" placeholder="Maintenance history, special instructions…" />
                    </x-ui.field>
                </div>
            </form>
            <x-slot:footer>
                <button type="button" class="btn" data-variant="outline" data-dialog-close>Cancel</button>
                <button type="submit" form="trailer-form" class="btn">{{ svg('lucide-check', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} Save trailer</button>
            </x-slot:footer>
        </x-ui.dialog>
    @endcanany
@endsection

@push('scripts')
    <script type="module">
        const table = window.dqTable('#trailers', {
            ajax: @json(route('admin.trailer.index')),
            primary: 1,
            order: [[7, 'desc']],
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'trailer_info', name: 'trailer_info' },
                { data: 'plate', name: 'plate', searchable: false },
                { data: 'equipment_group', orderable: false, searchable: false },
                { data: 'gvw', name: 'gvw', searchable: false },
                { data: 'owned_by', name: 'owned_by', searchable: false },
                { data: 'status', orderable: false, searchable: false },
                { data: 'created_at_formatted', name: 'created_at_formatted', searchable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-right' },
            ],
        });

        window.dqCrud.page({
            table,
            dialog: '#trailer-dialog',
            singular: 'trailer',
            routes: {
                store: @json(route('admin.trailer.store')),
                edit: @json(route('admin.trailer.edit', ':id')),
                update: @json(route('admin.trailer.update', ':id')),
                destroy: @json(route('admin.trailer.destroy', ':id')),
                restore: @json(route('admin.trailer.restore', ':id')),
            },
            afterFill: (form, record) => {
                // fill() leaves file inputs alone; show the saved photo with a Remove option
                form.elements.photo.value = '';
                const current = form.querySelector('[data-current-photo]');
                current.hidden = !record.photo_url;
                const image = current.querySelector('img');
                if (record.photo_url) image.src = record.photo_url;
                else image.removeAttribute('src');
            },
        });

        document.querySelectorAll('#trailer-form [name="vin"], #trailer-form [name="plate_number"]').forEach((input) => input.addEventListener('input', (event) => {
            event.target.value = event.target.value.toUpperCase();
        }));
    </script>
@endpush

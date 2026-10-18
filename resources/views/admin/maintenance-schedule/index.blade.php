@extends('layouts.main-layout')

@section('title', 'Maintenance Schedules')

@php
    $vehicleOptions = $vehicles->mapWithKeys(fn ($v) => [$v->id => trim($v->unit_no.' · '.$v->year.' '.$v->make.' '.$v->model)])->all();
    $categoryOptions = $maintenanceCategories->pluck('name', 'id')->all();
    $types = ['date' => 'By date', 'mileage' => 'By mileage', 'engine_hours' => 'By engine hours'];
    $statuses = ['active' => 'Active', 'paused' => 'Paused', 'completed' => 'Completed'];
@endphp

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Maintenance schedules" description="Recurring service by date, mileage or engine hours.">
            @can('scheduled.create')
                <x-slot:actions>
                    <x-ui.button icon="plus" data-action="create">Add schedule</x-ui.button>
                </x-slot:actions>
            @endcan
        </x-ui.page-header>

        <x-ui.datatable id="schedules" title="All schedules">
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.select data-dt-filter="vehicle_id" aria-label="Filter by vehicle" :options="['' => 'All vehicles'] + $vehicleOptions" class="w-auto! max-w-56" />
                    <x-ui.select data-dt-filter="category_id" aria-label="Filter by category" :options="['' => 'All categories'] + $categoryOptions" class="w-auto!" />
                    <x-ui.select data-dt-filter="schedule_type" aria-label="Filter by type" :options="['' => 'All types'] + $types" class="w-auto!" />
                    <x-ui.select data-dt-filter="status" aria-label="Filter by status" :options="['' => 'Any status'] + $statuses" class="w-auto!" />
                </div>
            </x-slot:toolbar>

            <th class="w-12">#</th>
            <th>Schedule</th>
            <th>Vehicle</th>
            <th>Interval</th>
            <th>Next due</th>
            <th>Status</th>
            <th class="text-right"><span class="sr-only">Actions</span></th>

            <x-slot:empty>
                <x-ui.empty-state icon="calendar-clock" title="No schedules yet" description="Plan recurring service so nothing is missed." class="border-0">
                    @can('scheduled.create')
                        <x-ui.button icon="plus" size="sm" data-action="create">Add schedule</x-ui.button>
                    @endcan
                </x-ui.empty-state>
            </x-slot:empty>
        </x-ui.datatable>
    </div>

    @canany(['scheduled.create', 'scheduled.edit'])
        <x-ui.dialog id="schedule-dialog" title="Add schedule" description="What is serviced, and how often." icon="calendar-clock" size="lg" :close-on-overlay="false">
            <form id="schedule-form" novalidate x-data="{ type: 'date' }" x-on:schedule-type.window="type = $event.detail">
                <div class="form-section">
                    <h3 class="form-section-title">{{ svg('lucide-wrench', '', ['aria-hidden' => 'true']) }} Service</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Maintenance category" name="maintenance_category_id" required>
                            <x-ui.select name="maintenance_category_id" :options="$categoryOptions" placeholder="Select category" required />
                        </x-ui.field>
                        <x-ui.field label="Title" name="title" hint="Optional. Shown instead of the category.">
                            <x-ui.input name="title" maxlength="255" placeholder="90-day inspection" />
                        </x-ui.field>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">{{ svg('lucide-repeat', '', ['aria-hidden' => 'true']) }} Interval</h3>
                    <x-ui.radio-group name="schedule_type" legend="Schedule by" :options="$types" value="date" required x-on:change="type = $event.target.value" />
                    <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                        <x-ui.field label="Vehicle" name="vehicle_id">
                            <x-ui.select name="vehicle_id" :options="$vehicleOptions" placeholder="All vehicles (global schedule)" />
                            <p class="text-sm text-muted-foreground" x-text="type === 'date' ? 'Leave empty to apply to every vehicle.' : 'Mileage and engine-hour schedules need a vehicle.'"></p>
                            <p data-vehicle-reading class="text-sm text-muted-foreground" hidden></p>
                        </x-ui.field>
                        <x-ui.field label="Every (days)" name="interval_days" required x-show="type === 'date'">
                            <x-ui.input type="number" name="interval_days" min="1" max="9999" placeholder="90" />
                        </x-ui.field>
                        <x-ui.field label="Every (miles)" name="interval_miles" required x-show="type === 'mileage'" x-cloak>
                            <x-ui.input type="number" name="interval_miles" min="1" max="999999" placeholder="15000" />
                        </x-ui.field>
                        <x-ui.field label="Every (engine hours)" name="interval_hours" required x-show="type === 'engine_hours'" x-cloak>
                            <x-ui.input type="number" name="interval_hours" min="1" max="999999" placeholder="500" />
                        </x-ui.field>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">{{ svg('lucide-sticky-note', '', ['aria-hidden' => 'true']) }} Details</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Status" name="status" required>
                            <x-ui.select name="status" :options="$statuses" required />
                        </x-ui.field>
                        <div class="hidden sm:block"></div>
                        <x-ui.field label="Description" name="description">
                            <x-ui.textarea name="description" rows="3" class="w-full" />
                        </x-ui.field>
                        <x-ui.field label="Internal notes" name="notes">
                            <x-ui.textarea name="notes" rows="3" class="w-full" />
                        </x-ui.field>
                    </div>
                </div>
            </form>
            <x-slot:footer>
                <button type="button" class="btn" data-variant="outline" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit" form="schedule-form" class="btn">{{ svg('lucide-check', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} Save schedule</button>
            </x-slot:footer>
        </x-ui.dialog>
    @endcanany

    <x-ui.dialog id="schedule-view-dialog" title="Schedule details" icon="calendar-clock" size="lg">
        <dl class="grid gap-4 sm:grid-cols-2">
            <x-ui.detail label="Category"><span data-view="category"></span></x-ui.detail>
            <x-ui.detail label="Title"><span data-view="title"></span></x-ui.detail>
            <x-ui.detail label="Vehicle" class="sm:col-span-2"><span data-view="vehicle"></span></x-ui.detail>
            <x-ui.detail label="Schedule by"><span data-view="type"></span></x-ui.detail>
            <x-ui.detail label="Interval"><span data-view="interval"></span></x-ui.detail>
            <x-ui.detail label="Next due" class="sm:col-span-2"><span data-view="next_due"></span> <span data-view="due" class="badge" data-tone="danger" hidden>Due now</span></x-ui.detail>
            <x-ui.detail label="Status"><span data-view="status" class="badge"></span></x-ui.detail>
            <x-ui.detail label="Description" class="sm:col-span-2"><span data-view="description" class="whitespace-pre-line"></span></x-ui.detail>
            <x-ui.detail label="Internal notes" class="sm:col-span-2"><span data-view="notes" class="whitespace-pre-line"></span></x-ui.detail>
        </dl>
        <x-slot:footer>
            <button type="button" class="btn" data-variant="outline" onclick="this.closest('dialog').close()">Close</button>
            @can('scheduled.edit')
                <button type="button" class="btn" data-mark-completed>{{ svg('lucide-circle-check', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} Record service done</button>
            @endcan
        </x-slot:footer>
    </x-ui.dialog>
@endsection

@push('scripts')
    <script type="module">
        const routes = {
            index: @json(route('admin.maintenance-schedule.index')),
            store: @json(route('admin.maintenance-schedule.store')),
            edit: @json(route('admin.maintenance-schedule.edit', ':id')),
            show: @json(route('admin.maintenance-schedule.show', ':id')),
            update: @json(route('admin.maintenance-schedule.update', ':id')),
            destroy: @json(route('admin.maintenance-schedule.destroy', ':id')),
            complete: @json(route('admin.maintenance-schedule.mark-completed', ':id')),
            vehicle: @json(route('admin.maintenance-schedule.get-vehicle-details', ':id')),
        };
        const tones = { active: 'success', paused: 'warning', completed: 'info' };
        const { request, at, report, confirm } = window.dqCrud;

        const table = window.dqTable('#schedules', {
            ajax: routes.index,
            primary: 1,
            order: [[4, 'asc']],
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'schedule_info', name: 'schedule_info' },
                { data: 'vehicle_info', orderable: false, searchable: false },
                { data: 'interval', name: 'interval', searchable: false },
                { data: 'next_due', name: 'next_due', searchable: false },
                { data: 'status', orderable: false, searchable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-right' },
            ],
        });

        const form = document.getElementById('schedule-form');
        const setType = (type) => window.dispatchEvent(new CustomEvent('schedule-type', { detail: type || 'date' }));

        // The vehicle's current reading, so the interval can be judged against it
        const showReading = async () => {
            const reading = form.querySelector('[data-vehicle-reading]');
            reading.hidden = true;
            const id = form.elements.vehicle_id.value;
            if (!id) return;
            const { ok, json } = await request(at(routes.vehicle, id));
            if (!ok) return;
            reading.textContent = `Now at ${Number(json.data.current_odometer || 0).toLocaleString()} mi · ${Number(json.data.engine_hours || 0).toLocaleString()} engine hours`;
            reading.hidden = false;
        };
        form?.elements.vehicle_id.addEventListener('change', showReading);

        const viewDialog = document.getElementById('schedule-view-dialog');
        const view = async (id) => {
            const { ok, json } = await request(at(routes.show, id));
            if (!ok) return window.notify('error', json.message || 'Could not load the schedule.');
            const data = json.data;
            const set = (key, value) => { viewDialog.querySelector(`[data-view="${key}"]`).textContent = value || '—'; };
            set('category', data.maintenance_category?.name);
            set('title', data.title);
            set('vehicle', data.vehicle ? data.vehicle.full_name : 'All vehicles');
            set('type', data.schedule_type_label);
            set('interval', data.interval_text);
            set('next_due', data.next_due_text);
            set('description', data.description);
            set('notes', data.notes);
            const status = viewDialog.querySelector('[data-view="status"]');
            status.textContent = data.status ? data.status[0].toUpperCase() + data.status.slice(1) : '—';
            status.dataset.tone = tones[data.status] ?? 'neutral';
            viewDialog.querySelector('[data-view="due"]').hidden = !data.is_due;
            viewDialog.dataset.id = id;
            viewDialog.showModal();
        };

        viewDialog.querySelector('[data-mark-completed]')?.addEventListener('click', () => {
            const id = viewDialog.dataset.id;
            viewDialog.close();
            confirm(
                { icon: 'question', title: 'Record service done?', text: 'The last service moves to today’s reading and the next due date is recalculated.', confirmText: 'Record', variant: 'primary' },
                async () => report(table, await request(at(routes.complete, id), 'POST'), 'Could not update the schedule.'),
            );
        });

        window.dqCrud.page({
            table,
            dialog: '#schedule-dialog',
            singular: 'schedule',
            routes,
            defaults: { schedule_type: 'date', status: 'active' },
            afterFill: (form, record) => { setType(record.schedule_type); showReading(); },
            actions: { view },
        });
    </script>
@endpush

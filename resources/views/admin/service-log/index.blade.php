@extends('layouts.main-layout')

@section('title', 'Service Logs')

@php
    $vehicleOptions = $vehicles->mapWithKeys(fn ($v) => [$v->id => trim($v->unit_no.' · '.$v->year.' '.$v->make.' '.$v->model)])->all();
    $categoryOptions = $maintenanceCategories->pluck('name', 'id')->all();
    $statuses = ['completed' => 'Completed', 'pending' => 'Pending', 'cancelled' => 'Cancelled'];
@endphp

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Service logs" description="Every service done on your vehicles, with cost and documents.">
            @can('maintenance.create')
                <x-slot:actions>
                    <x-ui.button icon="plus" data-action="create">Log service</x-ui.button>
                </x-slot:actions>
            @endcan
        </x-ui.page-header>

        <x-ui.datatable id="service-logs" title="All service logs">
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.select data-dt-filter="vehicle_id" aria-label="Filter by vehicle" :options="['' => 'All vehicles'] + $vehicleOptions" class="w-auto! max-w-56" />
                    <x-ui.select data-dt-filter="category_id" aria-label="Filter by category" :options="['' => 'All categories'] + $categoryOptions" class="w-auto!" />
                    <x-ui.select data-dt-filter="status" aria-label="Filter by status" :options="['' => 'Any status'] + $statuses" class="w-auto!" />
                    <x-ui.input type="date" data-dt-filter="date_from" aria-label="Service date from" class="w-auto!" />
                    <x-ui.input type="date" data-dt-filter="date_to" aria-label="Service date to" class="w-auto!" />
                    <x-ui.button variant="ghost" size="sm" data-dt-reset>Clear</x-ui.button>
                </div>
            </x-slot:toolbar>

            <th class="w-12">#</th>
            <th>Vehicle</th>
            <th>Service</th>
            <th>Odometer</th>
            <th>Cost</th>
            <th>Status</th>
            <th class="text-right"><span class="sr-only">Actions</span></th>

            <x-slot:empty>
                <x-ui.empty-state icon="wrench" title="No service logged yet" description="Log a service to keep the vehicle’s maintenance file complete." class="border-0">
                    @can('maintenance.create')
                        <x-ui.button icon="plus" size="sm" data-action="create">Log service</x-ui.button>
                    @endcan
                </x-ui.empty-state>
            </x-slot:empty>
        </x-ui.datatable>
    </div>

    @canany(['maintenance.create', 'maintenance.edit'])
        <x-ui.dialog id="service-log-dialog" title="Log service" description="The work done, the readings and the cost." icon="wrench" size="xl" :close-on-overlay="false">
            <form id="service-log-form" novalidate>
                <div class="form-section">
                    <h3 class="form-section-title">{{ svg('lucide-truck', '', ['aria-hidden' => 'true']) }} Service</h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.field label="Vehicle" name="vehicle_id" required class="sm:col-span-2">
                            <x-ui.select name="vehicle_id" :options="$vehicleOptions" placeholder="Select vehicle" required />
                        </x-ui.field>
                        <x-ui.field label="Service date" name="service_date" required>
                            <x-ui.input type="date" name="service_date" required max="{{ now()->toDateString() }}" />
                        </x-ui.field>
                        <x-ui.field label="Status" name="status" required>
                            <x-ui.select name="status" :options="$statuses" required />
                        </x-ui.field>
                    </div>
                </div>

                <fieldset class="form-section" id="f-maintenance_categories">
                    <legend class="form-section-title float-left w-full">{{ svg('lucide-list-checks', '', ['aria-hidden' => 'true']) }} Work done <span class="text-destructive" aria-hidden="true">*</span></legend>
                    <div class="clear-both grid max-h-52 gap-x-4 gap-y-2.5 overflow-y-auto rounded-lg border border-border bg-muted/20 p-3 custom-scrollbar sm:grid-cols-2 lg:grid-cols-3">
                        @forelse ($categoryOptions as $id => $name)
                            <x-ui.checkbox name="maintenance_categories[]" :id="'category-'.$id" :value="$id" :label="$name" />
                        @empty
                            <p class="text-sm text-muted-foreground sm:col-span-full">No maintenance categories yet. The platform team adds them under Lookups.</p>
                        @endforelse
                    </div>
                </fieldset>

                <div class="form-section">
                    <h3 class="form-section-title">{{ svg('lucide-gauge', '', ['aria-hidden' => 'true']) }} Readings and cost</h3>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <x-ui.field label="Odometer at service" name="odometer_at_service" required>
                            <x-ui.input type="number" name="odometer_at_service" required min="0" placeholder="mi" />
                        </x-ui.field>
                        <x-ui.field label="Current odometer" name="current_odometer" required>
                            <x-ui.input type="number" name="current_odometer" required min="0" placeholder="mi" />
                        </x-ui.field>
                        <x-ui.field label="Engine hrs at service" name="engine_hours_at_service">
                            <x-ui.input type="number" name="engine_hours_at_service" min="0" />
                        </x-ui.field>
                        <x-ui.field label="Current engine hrs" name="current_engine_hours">
                            <x-ui.input type="number" name="current_engine_hours" min="0" />
                        </x-ui.field>
                        <x-ui.field label="Total cost ($)" name="total_cost" required>
                            <x-ui.input type="number" name="total_cost" required min="0" step="0.01" placeholder="0.00" />
                        </x-ui.field>
                    </div>
                    <p class="-mt-2 text-xs text-muted-foreground">The current odometer is filled in from the vehicle and can’t be lower than the service reading.</p>
                </div>

                <div class="form-section">
                    <h3 class="form-section-title">{{ svg('lucide-paperclip', '', ['aria-hidden' => 'true']) }} Notes and documents</h3>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <x-ui.field label="Notes" name="maintenance_notes">
                            <x-ui.textarea name="maintenance_notes" rows="4" class="w-full" placeholder="What was done, parts used…" />
                        </x-ui.field>
                        <div class="grid content-start gap-2">
                            <x-ui.field label="Documents" name="documents" hint="Invoices or work orders: PDF, image or Word, up to 10 MB each.">
                                <x-ui.input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" />
                            </x-ui.field>
                            <ul data-existing-documents class="grid gap-1" role="list"></ul>
                        </div>
                    </div>
                </div>
            </form>
            <x-slot:footer>
                <button type="button" class="btn" data-variant="outline" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit" form="service-log-form" class="btn">{{ svg('lucide-check', '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }} Save service log</button>
            </x-slot:footer>
        </x-ui.dialog>
    @endcanany

    <x-ui.dialog id="service-log-view-dialog" title="Service details" icon="wrench" size="lg">
        <dl class="grid gap-4 sm:grid-cols-2">
            <x-ui.detail label="Vehicle" class="sm:col-span-2"><span data-view="vehicle"></span></x-ui.detail>
            <x-ui.detail label="Service date"><span data-view="date"></span></x-ui.detail>
            <x-ui.detail label="Status"><span data-view="status" class="badge"></span></x-ui.detail>
            <x-ui.detail label="Work done" class="sm:col-span-2"><span data-view="categories" class="flex flex-wrap gap-1"></span></x-ui.detail>
            <x-ui.detail label="Odometer at service"><span data-view="odometer"></span></x-ui.detail>
            <x-ui.detail label="Current odometer"><span data-view="current_odometer"></span></x-ui.detail>
            <x-ui.detail label="Engine hours at service"><span data-view="engine"></span></x-ui.detail>
            <x-ui.detail label="Current engine hours"><span data-view="current_engine"></span></x-ui.detail>
            <x-ui.detail label="Total cost"><span data-view="cost"></span></x-ui.detail>
            <x-ui.detail label="Notes" class="sm:col-span-2"><span data-view="notes" class="whitespace-pre-line"></span></x-ui.detail>
            <x-ui.detail label="Documents" class="sm:col-span-2"><ul data-view="documents" class="grid gap-1" role="list"></ul></x-ui.detail>
        </dl>
        <x-slot:footer>
            <button type="button" class="btn" data-variant="outline" onclick="this.closest('dialog').close()">Close</button>
        </x-slot:footer>
    </x-ui.dialog>
@endsection

@push('scripts')
    <script type="module">
        const routes = {
            index: @json(route('admin.service-log.index')),
            store: @json(route('admin.service-log.store')),
            edit: @json(route('admin.service-log.edit', ':id')),
            show: @json(route('admin.service-log.show', ':id')),
            update: @json(route('admin.service-log.update', ':id')),
            destroy: @json(route('admin.service-log.destroy', ':id')),
            vehicle: @json(route('admin.service-log.get-vehicle-details', ':id')),
            download: @json(route('admin.service-log.download-document', ':id')),
            deleteDocument: @json(route('admin.service-log.delete-document', ':id')),
        };
        const canEdit = @json(auth()->user()->can('maintenance.edit'));
        const tones = { completed: 'success', pending: 'warning', cancelled: 'danger' };
        const { request, at, report, confirm } = window.dqCrud;
        const number = (value, unit = '') => (value || value === 0) && value !== '' ? `${Number(value).toLocaleString()}${unit}` : '—';

        const table = window.dqTable('#service-logs', {
            ajax: routes.index,
            primary: 1,
            order: [[2, 'desc']],
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'vehicle_info', orderable: false, searchable: false },
                { data: 'service_info', name: 'service_info', searchable: false },
                { data: 'metrics', name: 'metrics', searchable: false },
                { data: 'cost', name: 'cost', searchable: false },
                { data: 'status', orderable: false, searchable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-right' },
            ],
        });

        // A document row: the name links to the download; in the form it can be removed.
        const documentItem = (doc, removable) => {
            const item = document.createElement('li');
            item.className = 'flex items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-sm';
            const link = document.createElement('a');
            link.href = at(routes.download, doc.id);
            link.target = '_blank';
            link.rel = 'noopener';
            link.className = 'min-w-0 truncate underline-offset-2 hover:underline';
            link.textContent = doc.original_name || 'Document';
            item.append(link);
            if (removable) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn text-destructive';
                remove.dataset.variant = 'ghost';
                remove.dataset.size = 'sm';
                remove.textContent = 'Remove';
                remove.addEventListener('click', () => confirm(
                    { title: 'Remove document?', text: `${doc.original_name || 'The document'} will be deleted.`, confirmButtonText: 'Remove', customClass: { confirmButton: 'swal2-deny' } },
                    async () => { if (report(table, await request(at(routes.deleteDocument, doc.id), 'DELETE'), 'Could not remove the document.')) item.remove(); },
                ));
                item.append(remove);
            }
            return item;
        };

        const form = document.getElementById('service-log-form');
        const fileInput = form?.querySelector('input[type="file"]');
        const existing = form?.querySelector('[data-existing-documents]');

        form?.elements.vehicle_id.addEventListener('change', async () => {
            const id = form.elements.vehicle_id.value;
            if (!id) return;
            const { ok, json } = await request(at(routes.vehicle, id));
            if (!ok) return;
            form.elements.current_odometer.value = json.data.current_odometer ?? '';
            form.elements.odometer_at_service.max = json.data.current_odometer ?? '';
        });

        window.dqCrud.page({
            table,
            dialog: '#service-log-dialog',
            singular: 'service log',
            routes,
            defaults: { status: 'completed', service_date: new Date().toISOString().slice(0, 10) },
            toForm: (record) => ({
                ...record,
                service_date: record.service_date ? String(record.service_date).slice(0, 10) : undefined,
                maintenance_categories: (record.maintenance_categories ?? []).map((category) => category.id),
            }),
            afterFill: (form, record) => {
                // store() reads documents[], update() reads new_documents[] (it keeps the existing ones)
                fileInput.name = record.id ? 'new_documents[]' : 'documents[]';
                fileInput.value = '';
                existing.replaceChildren(...(record.documents ?? []).map((doc) => documentItem(doc, canEdit)));
            },
            actions: { view },
        });

        const viewDialog = document.getElementById('service-log-view-dialog');
        async function view(id) {
            const { ok, json } = await request(at(routes.show, id));
            if (!ok) return window.notify('error', json.message || 'Could not load the service log.');
            const data = json.data;
            const set = (key, value) => { viewDialog.querySelector(`[data-view="${key}"]`).textContent = value || '—'; };
            set('vehicle', json.vehicle_info?.full_name ?? 'Deleted vehicle');
            set('date', data.service_date ? new Date(data.service_date).toLocaleDateString(undefined, { timeZone: 'UTC' }) : '');
            set('odometer', number(data.odometer_at_service, ' mi'));
            set('current_odometer', number(data.current_odometer, ' mi'));
            set('engine', number(data.engine_hours_at_service, ' hrs'));
            set('current_engine', number(data.current_engine_hours, ' hrs'));
            set('cost', data.total_cost !== null ? `$${Number(data.total_cost).toFixed(2)}` : '');
            set('notes', data.maintenance_notes);
            const status = viewDialog.querySelector('[data-view="status"]');
            status.textContent = data.status ? data.status[0].toUpperCase() + data.status.slice(1) : '—';
            status.dataset.tone = tones[data.status] ?? 'neutral';
            const categories = viewDialog.querySelector('[data-view="categories"]');
            categories.replaceChildren(...(data.maintenance_categories ?? []).map((category) => {
                const badge = document.createElement('span');
                badge.className = 'badge';
                badge.dataset.variant = 'secondary';
                badge.textContent = category.name;
                return badge;
            }));
            if (!categories.children.length) categories.textContent = '—';
            const documents = viewDialog.querySelector('[data-view="documents"]');
            documents.replaceChildren(...(data.documents ?? []).map((doc) => documentItem(doc, false)));
            if (!documents.children.length) documents.textContent = 'No documents uploaded';
            viewDialog.showModal();
        }
    </script>
@endpush

@extends($platform ? 'layouts.platform' : 'layouts.main-layout')

@section('title', 'Document Types')

@php
    $routes = $platform
        ? ['index' => route('platform.lookups.document-types.index'), 'store' => route('platform.lookups.document-types.store'), 'item' => route('platform.lookups.document-types.show', ':id'), 'toggle' => route('platform.lookups.document-types.toggle-status', ':id')]
        : ['index' => route('admin.settings.document-types.index'), 'companyToggle' => route('admin.settings.document-types.company-toggle', ':id')];
    $breadcrumbs = [['label' => $platform ? 'Lookups' : 'Settings'], ['label' => 'Document types']];
@endphp

@section('content')
    <div @class(['p-4 sm:p-6 lg:p-8' => ! $platform])>
        <x-ui.page-header title="Document types"
            :description="$showCompanyColumn ? 'Switch off the document types your company doesn’t use. They stop counting towards your compliance.' : 'The documents drivers, vehicles and trailers can be asked for.'">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="$breadcrumbs" />
            </x-slot:breadcrumbs>
            @can('platform.settings.manage')
                <x-slot:actions>
                    <x-ui.button icon="plus" data-action="create">Add document type</x-ui.button>
                </x-slot:actions>
            @endcan
        </x-ui.page-header>

        <x-ui.datatable id="document-types" title="All document types">
            <x-slot:toolbar>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.select data-dt-filter="module" aria-label="Filter by module" :options="['' => 'All modules'] + $modules" class="w-auto!" data-size="sm" />
                    <x-ui.select data-dt-filter="status" aria-label="Filter by status" :options="['' => 'Any status', 'active' => 'Active', 'inactive' => 'Inactive']" class="w-auto!" data-size="sm" />
                </div>
            </x-slot:toolbar>

            <th class="w-12">#</th>
            <th>Name</th>
            <th>Module</th>
            <th>Status</th>
            <th>Created</th>
            @if ($showCompanyColumn)
                <th>My company</th>
            @endif
            <th class="text-right"><span class="sr-only">Actions</span></th>

            <x-slot:empty>
                <x-ui.empty-state icon="file-text" title="No document types yet" description="Add the documents drivers, vehicles and trailers need." class="border-0" />
            </x-slot:empty>
        </x-ui.datatable>
    </div>

    @can('platform.settings.manage')
        <x-ui.dialog id="document-type-dialog" title="New document type" description="Where it is asked for and whether it is in use." icon="file-text" size="md">
            <form id="document-type-form" class="grid gap-4" novalidate>
                <input type="hidden" name="id">
                <x-ui.field label="Name" name="name" required>
                    <x-ui.input name="name" required maxlength="255" autocomplete="off" placeholder="For example Driver license" />
                </x-ui.field>
                <x-ui.field label="Module" name="module" required>
                    <x-ui.select name="module" :options="$modules" placeholder="Select module" required />
                </x-ui.field>
                <x-ui.radio-group name="status" legend="Status" :options="['1' => 'Active', '0' => 'Inactive']" value="1" required />
            </form>
            <x-slot:footer>
                <button type="button" class="btn" data-variant="outline" onclick="this.closest('dialog').close()">Cancel</button>
                <button type="submit" form="document-type-form" class="btn">Save</button>
            </x-slot:footer>
        </x-ui.dialog>
    @endcan
@endsection

@push('scripts')
    <script type="module">
        const routes = @json($routes);
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const at = (url, id) => url.replace(':id', encodeURIComponent(id));

        const table = window.dqTable('#document-types', {
            ajax: routes.index,
            primary: 1,
            order: [[1, 'asc']],
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'name', name: 'name', className: 'font-medium text-foreground' },
                { data: 'module_label', name: 'module_label', searchable: false },
                { data: 'status_badge', orderable: false, searchable: false },
                { data: 'created_at', name: 'created_at', searchable: false },
                @if ($showCompanyColumn)
                    { data: 'company_enabled', orderable: false, searchable: false },
                @endif
                { data: 'action', orderable: false, searchable: false, className: 'text-right' },
            ],
        });

        const request = (url, method, body) => fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body,
        }).then(async (response) => ({ ok: response.ok, status: response.status, json: await response.json().catch(() => ({})) }));

        const done = ({ ok, json }, fallback) => {
            window.notify(ok ? 'success' : 'error', json.message || (ok ? 'Saved' : fallback));
            if (ok) table.ajax.reload(null, false);
            return ok;
        };

        const dialog = document.getElementById('document-type-dialog');
        const form = document.getElementById('document-type-form');

        const setErrors = (errors = {}) => {
            form.querySelectorAll('.js-error').forEach((el) => el.remove());
            form.querySelectorAll('[aria-invalid]').forEach((el) => el.removeAttribute('aria-invalid'));
            Object.entries(errors).forEach(([name, messages]) => {
                const control = form.elements[name];
                const field = (control instanceof RadioNodeList ? control[0] : control)?.closest('.field, fieldset');
                if (!field) return;
                const error = document.createElement('p');
                error.className = 'js-error text-sm text-destructive';
                error.setAttribute('role', 'alert');
                error.textContent = messages[0];
                field.append(error);
                (control instanceof RadioNodeList ? [...control] : [control]).forEach((el) => el.setAttribute('aria-invalid', 'true'));
            });
        };

        const open = (title, data = { id: '', name: '', module: '', status: true }) => {
            dialog.querySelector('h2').textContent = title;
            form.elements.id.value = data.id;
            form.elements.name.value = data.name;
            form.elements.module.value = data.module;
            form.elements.status.value = data.status ? '1' : '0';
            setErrors();
            dialog.showModal();
            form.elements.name.focus();
        };

        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;
            const { action, id, name } = button.dataset;

            if (action === 'create') {
                open('New document type');
            } else if (action === 'edit') {
                const { ok, json } = await request(at(routes.item, id), 'GET');
                if (ok) open('Edit document type', json.data);
                else window.notify('error', json.message || 'Could not load the document type.');
            } else if (action === 'delete') {
                confirmThen({ title: 'Delete document type?', text: `“${name}” will be removed. This cannot be undone.`, confirmButtonText: 'Delete', customClass: { confirmButton: 'swal2-deny' } },
                    async () => done(await request(at(routes.item, id), 'DELETE'), 'Could not delete the document type.'));
            } else if (action === 'toggle-status') {
                const active = button.dataset.active === '1';
                confirmThen({ title: active ? 'Deactivate document type?' : 'Activate document type?', text: active ? `“${name}” will no longer be requested.` : `“${name}” will be requested again.`, confirmButtonText: active ? 'Deactivate' : 'Activate' },
                    async () => done(await request(at(routes.toggle, id), 'POST'), 'Could not change the status.'));
            } else if (action === 'company-toggle') {
                button.disabled = true;
                done(await request(at(routes.companyToggle, id), 'POST'), 'Could not change the setting.');
                button.disabled = false;
            }
        });

        function confirmThen(options, onConfirm) {
            Swal.fire({ icon: 'warning', showCancelButton: true, reverseButtons: true, ...options })
                .then((result) => result.isConfirmed && onConfirm());
        }

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const id = form.elements.id.value;
            const body = new FormData(form);
            body.delete('id');
            if (id) body.append('_method', 'PUT');

            const submit = dialog.querySelector('button[type="submit"]');
            submit.disabled = true;
            const response = await request(id ? at(routes.item, id) : routes.store, 'POST', body);
            submit.disabled = false;

            if (response.status === 422) {
                setErrors(response.json.errors);
            } else if (done(response, 'Something went wrong. Please try again.')) {
                dialog.close();
            }
        });
    </script>
@endpush

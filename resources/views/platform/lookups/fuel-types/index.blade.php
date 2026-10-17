@extends('layouts.platform')

@section('title', 'Fuel Types')

@section('content')
    <x-ui.page-header title="Fuel types" description="The fuel or power sources companies can pick for their vehicles.">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Lookups'], ['label' => 'Fuel types']]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-ui.button icon="plus" data-action="create">Add fuel type</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.datatable id="fuel-types" title="All fuel types">
        <th class="w-12">#</th>
        <th>Name</th>
        <th>Created</th>
        <th>Updated</th>
        <th class="text-right"><span class="sr-only">Actions</span></th>

        <x-slot:empty>
            <x-ui.empty-state icon="fuel" title="No fuel types yet" description="Add the fuel types companies can choose from." class="border-0" />
        </x-slot:empty>
    </x-ui.datatable>

    <x-ui.dialog id="fuel-type-dialog" title="Add fuel type" class="w-full sm:max-w-md">
        <form id="fuel-type-form" class="grid gap-4" novalidate>
            <input type="hidden" name="id">
            <x-ui.field label="Name" name="name" required hint="For example Diesel, Petrol, Electric or Hybrid.">
                <x-ui.input name="name" required maxlength="255" autocomplete="off" />
            </x-ui.field>
        </form>
        <x-slot:footer>
            <button type="button" class="btn" data-variant="outline" onclick="this.closest('dialog').close()">Cancel</button>
            <button type="submit" form="fuel-type-form" class="btn">Save</button>
        </x-slot:footer>
    </x-ui.dialog>
@endsection

@push('scripts')
    <script type="module">
        const routes = {
            index: @json(route('platform.lookups.fuel-types.index')),
            store: @json(route('platform.lookups.fuel-types.store')),
            item: @json(route('platform.lookups.fuel-types.update', ':id')),
            edit: @json(route('platform.lookups.fuel-types.edit', ':id')),
        };
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const dialog = document.getElementById('fuel-type-dialog');
        const form = document.getElementById('fuel-type-form');
        const nameField = form.querySelector('.field');
        const nameInput = form.elements.name;

        const table = window.dqTable('#fuel-types', {
            ajax: routes.index,
            primary: 1,
            order: [[1, 'asc']],
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'name', name: 'name', className: 'font-medium text-foreground' },
                { data: 'created_at_formatted', name: 'created_at_formatted', searchable: false },
                { data: 'updated_at_formatted', name: 'updated_at_formatted', searchable: false },
                { data: 'action', orderable: false, searchable: false, className: 'text-right' },
            ],
        });

        const request = (url, method, body) => fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body,
        }).then(async (response) => ({ ok: response.ok, status: response.status, json: await response.json().catch(() => ({})) }));

        const setError = (message) => {
            nameField.querySelector('[role="alert"]')?.remove();
            nameInput.removeAttribute('aria-invalid');
            if (!message) return;
            const error = document.createElement('p');
            error.setAttribute('role', 'alert');
            error.className = 'text-sm text-destructive';
            error.textContent = message;
            nameField.append(error);
            nameInput.setAttribute('aria-invalid', 'true');
        };

        const open = (title, id = '', name = '') => {
            dialog.querySelector('h2').textContent = title;
            form.elements.id.value = id;
            nameInput.value = name;
            setError(null);
            dialog.showModal();
            nameInput.focus();
        };

        document.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-action]');
            if (!button) return;

            if (button.dataset.action === 'create') {
                open('Add fuel type');
            } else if (button.dataset.action === 'edit') {
                const { ok, json } = await request(routes.edit.replace(':id', button.dataset.id), 'GET');
                if (ok) open('Edit fuel type', json.data.id, json.data.name);
                else window.notify('error', 'Could not load the fuel type');
            } else if (button.dataset.action === 'delete') {
                remove(button.dataset.id, button.dataset.name);
            }
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const id = form.elements.id.value;
            const body = new FormData();
            body.append('name', nameInput.value.trim());
            if (id) body.append('_method', 'PUT');

            const submit = dialog.querySelector('button[type="submit"]');
            submit.disabled = true;
            const { ok, status, json } = await request(id ? routes.item.replace(':id', id) : routes.store, 'POST', body);
            submit.disabled = false;

            if (ok) {
                dialog.close();
                window.notify('success', json.message || 'Saved');
                table.ajax.reload(null, false);
            } else if (status === 422) {
                setError(json.errors?.name?.[0] ?? 'Please check the name.');
            } else {
                window.notify('error', json.message || 'Something went wrong. Please try again.');
            }
        });

        function remove(id, name) {
            Swal.fire({
                title: 'Delete fuel type?',
                text: `“${name}” will be removed. This cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                reverseButtons: true,
                customClass: { confirmButton: 'swal2-deny' },
            }).then(async (result) => {
                if (!result.isConfirmed) return;
                const { ok, json } = await request(routes.item.replace(':id', id), 'DELETE');
                if (ok) {
                    window.notify('success', json.message || 'Deleted');
                    table.ajax.reload(null, false);
                } else {
                    window.notify('error', json.message || 'Could not delete the fuel type.');
                }
            });
        }
    </script>
@endpush

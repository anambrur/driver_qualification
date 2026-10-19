@extends('layouts.main-layout')

@section('title', $role ? 'Edit Role' : 'Create Role')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header :title="$role ? 'Edit Role: '.$role->name : 'Create Role'" description="You can grant only permissions you hold yourself.">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Roles', 'href' => route('admin.roles.index')], ['label' => $role ? $role->name : 'Create Role']]" />
            </x-slot:breadcrumbs>
        </x-ui.page-header>

        <x-ui.flash />

        @php($held = old('permissions', $role?->permissions->pluck('name')->all() ?? []))

        <form action="{{ $role ? route('admin.roles.update', $role->id) : route('admin.roles.store') }}" method="POST" class="space-y-6">
            @csrf
            @if ($role)
                @method('PUT')
            @endif

            <x-ui.card title="Role Information">
                <x-ui.field label="Role Name" for="name" name="name" required :hint="$role?->is_system ? 'System roles keep their name.' : null" class="max-w-md">
                    <x-ui.input name="name" id="name" required placeholder="e.g., Content Manager, Editor" :value="$role?->name" :readonly="(bool) $role?->is_system" />
                </x-ui.field>
            </x-ui.card>

            <x-ui.card title="Permissions" description="Select permissions for this role. A group checkbox selects every permission in it.">
                @error('permissions.*')
                    <x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>
                @enderror

                @if (count($permissions) === 0)
                    <x-ui.empty-state icon="key-round" title="No permissions available" />
                @else
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($permissions as $group => $groupPermissions)
                            @php($slug = Str::slug($group))
                            <fieldset class="overflow-hidden rounded-lg border">
                                <legend class="sr-only">{{ $group }}</legend>
                                <label for="group-{{ $slug }}" class="label flex cursor-pointer items-center gap-3 border-b bg-muted/50 px-4 py-3 capitalize">
                                    <input type="checkbox" id="group-{{ $slug }}" class="group-toggle input" data-group="{{ $slug }}">
                                    {{ $group }}
                                </label>
                                <div class="grid max-h-60 gap-2 overflow-y-auto p-4">
                                    @foreach ($groupPermissions as $permission)
                                        <label for="perm-{{ Str::slug($permission) }}" class="label flex cursor-pointer items-center gap-3 font-normal" title="{{ $permission }}">
                                            <input type="checkbox" name="permissions[]" id="perm-{{ Str::slug($permission) }}" value="{{ $permission }}"
                                                class="permission-checkbox input" data-group="{{ $slug }}" @checked(in_array($permission, $held))>
                                            <span class="truncate">{{ Str::after($permission, $group.'.') }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-ui.button variant="outline" :href="route('admin.roles.index')">Cancel</x-ui.button>
                <x-ui.button type="submit" icon="check">{{ $role ? 'Update Role' : 'Create Role' }}</x-ui.button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize all group toggles
            document.querySelectorAll('.group-toggle').forEach(groupToggle => {
                const groupSlug = groupToggle.dataset.group;
                const permissionCheckboxes = document.querySelectorAll(
                    `.permission-checkbox[data-group="${groupSlug}"]`);

                // Function to update group toggle state
                const updateGroupToggle = () => {
                    const totalCheckboxes = permissionCheckboxes.length;
                    const checkedCount = Array.from(permissionCheckboxes).filter(cb => cb.checked)
                        .length;

                    if (checkedCount === 0) {
                        groupToggle.checked = false;
                        groupToggle.indeterminate = false;
                    } else if (checkedCount === totalCheckboxes) {
                        groupToggle.checked = true;
                        groupToggle.indeterminate = false;
                    } else {
                        groupToggle.checked = false;
                        groupToggle.indeterminate = true;
                    }
                };

                // Update group toggle when any permission checkbox changes
                permissionCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', updateGroupToggle);
                });

                // Toggle all permissions when group checkbox is clicked
                groupToggle.addEventListener('click', function(e) {
                    const isChecked = this.checked;

                    permissionCheckboxes.forEach(checkbox => {
                        checkbox.checked = isChecked;
                    });
                });

                // Initial update
                updateGroupToggle();
            });
        });
    </script>
@endpush

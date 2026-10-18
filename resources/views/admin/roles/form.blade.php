@extends('layouts.main-layout')

@section('title', $role ? 'Edit Role' : 'Create Role')

@section('content')
    <div class="p-4 mx-auto md:p-6">
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-foreground dark:text-white/90">{{ $role ? 'Edit Role: '.$role->name : 'Create Role' }}</h2>
                    <p class="text-muted-foreground mt-2">You can grant only permissions you hold yourself.</p>
                </div>

                <a href="{{ route('admin.roles.index') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-input bg-card px-4 py-2.5 text-sm font-medium text-foreground shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-primary/20 focus:ring-offset-2 mt-4 sm:mt-0">
                    <x-ui.icon name="arrow-left" class="inline-block size-4 align-[-0.125em] mr-2" />Back to Roles
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                <div class="flex items-center">
                    <x-ui.icon name="circle-check" class="inline-block size-4 align-[-0.125em] text-green-500 mr-3" />
                    <p class="text-green-700 dark:text-green-400">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        <!-- Error Messages -->
        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                <div class="flex items-center">
                    <x-ui.icon name="circle-alert" class="inline-block size-4 align-[-0.125em] text-red-500 mr-3" />
                    <div>
                        <p class="font-medium text-red-700 dark:text-red-400">Please fix the following errors:</p>
                        <ul class="mt-1 list-disc list-inside text-sm text-red-600 dark:text-red-400">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <form action="{{ $role ? route('admin.roles.update', $role->id) : route('admin.roles.store') }}" method="POST">
            @csrf
            @if ($role)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Role Information Card -->
                    <div
                        class="rounded-2xl border border-border bg-card shadow-sm dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                <x-ui.icon name="info" class="inline-block size-4 align-[-0.125em] mr-2" />Role Information
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-border p-5 sm:p-6">
                            <!-- Role Name -->
                            <div>
                                <label for="name"
                                    class="mb-1.5 block text-sm font-medium text-foreground">
                                    Role Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="name" name="name" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="e.g., Content Manager, Editor" value="{{ old('name', $role?->name) }}" @readonly($role?->is_system) />
                                @if ($role?->is_system)
                                    <p class="mt-1 text-sm text-muted-foreground">System roles keep their name.</p>
                                @endif
                                @error('name')
                                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Permissions -->
                <div class="space-y-6">
                    <!-- Permissions Card -->
                    <div
                        class="rounded-2xl border border-border bg-card shadow-sm dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                <x-ui.icon name="key-round" class="inline-block size-4 align-[-0.125em] mr-2" />Permissions
                            </h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Select permissions for this role
                            </p>
                        </div>
                        <div class="border-t border-border p-5 sm:p-6">
                            @error('permissions.*')
                                <div
                                    class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                </div>
                            @enderror

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach ($permissions as $group => $groupPermissions)
                                    <div class="rounded-lg border border-border">
                                        <!-- Group Header -->
                                        <div
                                            class="border-b border-border bg-muted/50 px-4 py-3">
                                            <div class="flex items-center">
                                                <input type="checkbox" id="group-{{ Str::slug($group) }}"
                                                    class="group-toggle h-4 w-4 rounded border-input text-primary focus:ring-primary"
                                                    data-group="{{ Str::slug($group) }}">
                                                <label for="group-{{ Str::slug($group) }}"
                                                    class="ml-3 block text-sm font-medium text-foreground capitalize cursor-pointer">
                                                    {{ $group }}
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Group Permissions -->
                                        <div class="max-h-60 space-y-2 overflow-y-auto p-4">
                                            @foreach ($groupPermissions as $permission)
                                                <div class="flex items-center">
                                                    <input type="checkbox" name="permissions[]"
                                                        id="perm-{{ Str::slug($permission) }}"
                                                        value="{{ $permission }}"
                                                        class="permission-checkbox h-4 w-4 rounded border-input text-primary focus:ring-primary"
                                                        data-group="{{ Str::slug($group) }}"
                                                        @checked(in_array($permission, old('permissions', $role?->permissions->pluck('name')->all() ?? [])))>
                                                    <label for="perm-{{ Str::slug($permission) }}"
                                                        class="ml-3 text-sm text-foreground cursor-pointer truncate"
                                                        title="{{ $permission }}">
                                                        {{ Str::after($permission, $group . '.') }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if (count($permissions) === 0)
                                <div class="text-center py-8">
                                    <div
                                        class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                                        <x-ui.icon name="key-round" class="inline-block size-4 align-[-0.125em] text-muted-foreground" />
                                    </div>
                                    <p class="text-sm text-muted-foreground">No permissions available</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3">
                <a href="{{ route('admin.roles.index') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-input bg-card px-4 py-2.5 text-sm font-medium text-foreground shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-primary/20 focus:ring-offset-2">
                    <x-ui.icon name="x" class="inline-block size-4 align-[-0.125em] mr-2" />Cancel
                </a>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-transparent bg-primary px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-primary/90 focus:outline-hidden focus:ring-2 focus:ring-primary/20 focus:ring-offset-2">
                    <x-ui.icon name="save" class="inline-block size-4 align-[-0.125em] mr-2" />{{ $role ? 'Update Role' : 'Create Role' }}
                </button>
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

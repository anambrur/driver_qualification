@extends('layouts.main-layout')

@section('title', 'Create Role')

@section('content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Create New Role</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Add a new role to your system</p>
                </div>

                <a href="{{ route('admin.roles.index') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 mt-4 sm:mt-0">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Roles
                </a>
            </div>
        </div>

        <!-- Error Messages -->
        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
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

        <form action="{{ route('admin.roles.store') }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Left Column -->
                <div class="space-y-6">
                    <!-- Role Information Card -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                <i class="fas fa-info-circle mr-2"></i>Role Information
                            </h3>
                        </div>
                        <div class="space-y-6 border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
                            <!-- Role Name -->
                            <div>
                                <label for="name"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Role Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="name" name="name" required
                                    class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                    placeholder="e.g., Content Manager, Editor" value="{{ old('name') }}" />
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
                        class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                        <div class="px-5 py-4 sm:px-6 sm:py-5">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                <i class="fas fa-key mr-2"></i>Permissions
                            </h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Select permissions for this role</p>
                        </div>
                        <div class="border-t border-gray-100 p-5 sm:p-6 dark:border-gray-800">
                            @error('permissions.*')
                                <div
                                    class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                </div>
                            @enderror

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach ($permissions as $group => $groupPermissions)
                                    <div class="rounded-lg border border-gray-200 dark:border-gray-800">
                                        <!-- Group Header -->
                                        <div
                                            class="border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/50">
                                            <div class="flex items-center">
                                                <input type="checkbox" id="group-{{ Str::slug($group) }}"
                                                    class="group-toggle h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700 dark:focus:ring-brand-600"
                                                    data-group="{{ Str::slug($group) }}">
                                                <label for="group-{{ Str::slug($group) }}"
                                                    class="ml-3 block text-sm font-medium text-gray-700 capitalize dark:text-gray-300 cursor-pointer">
                                                    {{ $group }}
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Group Permissions -->
                                        <div class="max-h-60 space-y-2 overflow-y-auto p-4">
                                            @foreach ($groupPermissions as $permission)
                                                <div class="flex items-center">
                                                    <input type="checkbox" name="permissions[]"
                                                        id="perm-{{ $permission['id'] }}"
                                                        value="{{ $permission['name'] }}"
                                                        class="permission-checkbox h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700 dark:focus:ring-brand-600"
                                                        data-group="{{ Str::slug($group) }}"
                                                        {{ in_array($permission['name'], old('permissions', [])) ? 'checked' : '' }}>
                                                    <label for="perm-{{ $permission['id'] }}"
                                                        class="ml-3 text-sm text-gray-700 dark:text-gray-400 cursor-pointer truncate"
                                                        title="{{ $permission['name'] }}">
                                                        {{ Str::after($permission['name'], $group . '.') }}
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
                                        class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                                        <i class="fas fa-key text-gray-400 dark:text-gray-500"></i>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">No permissions available</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="mt-8 flex justify-end space-x-3">
                <a href="{{ route('admin.roles.index') }}"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                    <i class="fas fa-save mr-2"></i>Create Role
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

            // Debug: Log group toggle initialization
            console.log('Group toggles initialized:', document.querySelectorAll('.group-toggle').length);
        });
    </script>
@endpush

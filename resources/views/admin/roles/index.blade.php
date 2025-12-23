@extends('layouts.main-layout')

@section('title', 'Roles Management')

@section('content')
    <div class="p-4 mx-auto max-w-7xl md:p-6">
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">Roles Management</h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-2">Manage user roles and permissions</p>
                </div>

                @can('roles.create')
                    <a href="{{ route('admin.roles.create') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 mt-4 sm:mt-0">
                        <i class="fas fa-plus mr-2"></i>Add New Role
                    </a>
                @endcan
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="mb-6 rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-3"></i>
                    <p class="text-green-700 dark:text-green-400">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                    <p class="text-red-700 dark:text-red-400">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <!-- Roles Table Card -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="px-5 py-4 sm:px-6 sm:py-5">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    <i class="fas fa-user-shield mr-2"></i>Roles List
                </h3>
            </div>

            <div class="border-t border-gray-100 dark:border-gray-800">
                @if ($roles->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        ID
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Role Name
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Permissions
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider dark:text-gray-400">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                @foreach ($roles as $role)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors duration-150">
                                        <td
                                            class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-800 dark:text-white/90">
                                            #{{ $role->id }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span
                                                class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $role->name }}</span>
                                        </td>
                                        <td class="px-6 py-4">
                                            @if ($role->permissions && $role->permissions->count() > 0)
                                                <div class="flex flex-wrap gap-1.5">
                                                    @foreach ($role->permissions as $permission)
                                                        <span
                                                            class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                                            {{ $permission->name }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-sm text-gray-500 dark:text-gray-400">No permissions</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium space-x-2">
                                            @can('roles.edit')
                                                <a href="{{ route('admin.roles.edit', $role->id) }}"
                                                    class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                                                    <i class="fas fa-edit mr-1.5"></i>Edit
                                                </a>
                                            @endcan

                                            @can('roles.delete')
                                                <button
                                                    onclick="deleteRole({{ $role->id }}, '{{ addslashes($role->name) }}')"
                                                    class="inline-flex items-center rounded-lg border border-transparent bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 shadow-theme-xs hover:bg-red-100 focus:outline-hidden focus:ring-2 focus:ring-red-500/20 focus:ring-offset-2 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30">
                                                    <i class="fas fa-trash-alt mr-1.5"></i>Delete
                                                </button>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12">
                        <div
                            class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                            <i class="fas fa-user-shield text-gray-400 dark:text-gray-500"></i>
                        </div>
                        <h3 class="mb-2 text-sm font-medium text-gray-900 dark:text-white/90">No roles found</h3>
                        <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first role.
                        </p>
                        @can('roles.create')
                            <a href="{{ route('admin.roles.create') }}"
                                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-brand-500/20 focus:ring-offset-2">
                                <i class="fas fa-plus mr-2"></i>Create Role
                            </a>
                        @endcan
                    </div>
                @endif
            </div>

            {{-- @if ($roles->hasPages())
                <div class="border-t border-gray-100 px-6 py-4 dark:border-gray-800">
                    {{ $roles->links() }}
                </div>
            @endif --}}
        </div>
    </div>

    <!-- Delete Form (Hidden) -->
    <form id="deleteRoleForm" method="POST" class="hidden">
        @csrf
        @method('DELETE')
    </form>
@endsection

@push('scripts')
    <script>
        function deleteRole(id, name) {
            Swal.fire({
                title: 'Delete Role?',
                html: `Are you sure you want to delete <strong>"${name}"</strong>?<br>This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'rounded-lg',
                    cancelButton: 'rounded-lg'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('deleteRoleForm');
                    form.action = `/admin/roles/${id}`;
                    form.submit();
                }
            });
        }
    </script>
@endpush

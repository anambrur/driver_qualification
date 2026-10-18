@extends('layouts.main-layout')

@section('title', 'Roles Management')

@section('content')
    <div class="p-4 mx-auto md:p-6">
        <!-- Header Section -->
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-foreground dark:text-white/90">Roles Management</h2>
                    <p class="text-muted-foreground mt-2">Roles of your company. Changes apply to your company only.</p>
                </div>

                @can('roles.create')
                    <a href="{{ route('admin.roles.create') }}"
                        class="inline-flex items-center justify-center rounded-lg border border-transparent bg-primary px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-primary/90 focus:outline-hidden focus:ring-2 focus:ring-primary/20 focus:ring-offset-2 mt-4 sm:mt-0">
                        <x-ui.icon name="plus" class="inline-block size-4 align-[-0.125em] mr-2" />Add New Role
                    </a>
                @endcan
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

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                <div class="flex items-center">
                    <x-ui.icon name="circle-alert" class="inline-block size-4 align-[-0.125em] text-red-500 mr-3" />
                    <p class="text-red-700 dark:text-red-400">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        <!-- Roles Table Card -->
        <div class="rounded-2xl border border-border bg-card shadow-sm dark:bg-white/[0.03]">
            <div class="px-5 py-4 sm:px-6 sm:py-5">
                <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                    <x-ui.icon name="shield-user" class="inline-block size-4 align-[-0.125em] mr-2" />Roles List
                </h3>
            </div>

            <div class="border-t border-border">
                @if ($roles->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-border">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                        ID
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                        Role Name
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                        Permissions
                                    </th>
                                    <th scope="col"
                                        class="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($roles as $role)
                                    <tr class="hover:bg-muted/50 transition-colors duration-150">
                                        <td
                                            class="whitespace-nowrap px-6 py-4 text-sm font-medium text-foreground dark:text-white/90">
                                            #{{ $role->id }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4">
                                            <span
                                                class="text-sm font-medium text-foreground dark:text-white/90">{{ $role->name }}</span>
                                            @if ($role->isLocked())
                                                <span class="ml-2 inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-foreground">Locked</span>
                                            @elseif ($role->is_system)
                                                <span class="ml-2 inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">System</span>
                                            @endif
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
                                                <span class="text-sm text-muted-foreground">No permissions</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium space-x-2">
                                            @if (auth()->user()->can('roles.edit') && in_array($role->id, $manageable))
                                                <a href="{{ route('admin.roles.edit', $role->id) }}"
                                                    class="inline-flex items-center rounded-lg border border-input bg-card px-3 py-1.5 text-xs font-medium text-foreground shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-primary/20 focus:ring-offset-2">
                                                    <x-ui.icon name="pencil" class="inline-block size-4 align-[-0.125em] mr-1.5" />Edit
                                                </a>
                                            @endif

                                            @if (auth()->user()->can('roles.delete') && in_array($role->id, $manageable) && ! ($role->is_system && $role->in_use))
                                                <button type="button" data-action="delete-role"
                                                    data-url="{{ route('admin.roles.destroy', $role->id) }}"
                                                    data-name="{{ $role->name }}"
                                                    class="inline-flex items-center rounded-lg border border-transparent bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 shadow-theme-xs hover:bg-red-100 focus:outline-hidden focus:ring-2 focus:ring-red-500/20 focus:ring-offset-2 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30">
                                                    <x-ui.icon name="trash-2" class="inline-block size-4 align-[-0.125em] mr-1.5" />Delete
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12">
                        <div
                            class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-muted">
                            <x-ui.icon name="shield-user" class="inline-block size-4 align-[-0.125em] text-muted-foreground" />
                        </div>
                        <h3 class="mb-2 text-sm font-medium text-foreground dark:text-white/90">No roles found</h3>
                        <p class="mb-6 text-sm text-muted-foreground">Get started by creating your first role.
                        </p>
                        @can('roles.create')
                            <a href="{{ route('admin.roles.create') }}"
                                class="inline-flex items-center justify-center rounded-lg border border-transparent bg-primary px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-primary/90 focus:outline-hidden focus:ring-2 focus:ring-primary/20 focus:ring-offset-2">
                                <x-ui.icon name="plus" class="inline-block size-4 align-[-0.125em] mr-2" />Create Role
                            </a>
                        @endcan
                    </div>
                @endif
            </div>

            {{-- @if ($roles->hasPages())
                <div class="border-t border-border px-6 py-4">
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
        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-action="delete-role"]');
            if (button) {
                deleteRole(button.dataset.url, button.dataset.name);
            }
        });

        function deleteRole(url, name) {
            showAppConfirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`, {
                title: 'Delete Role?', icon: 'warning', confirmButtonText: 'Yes, delete it!', variant: 'destructive',
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.getElementById('deleteRoleForm');
                    form.action = url;
                    form.submit();
                }
            });
        }
    </script>
@endpush

@extends('layouts.main-layout')

@section('title', 'Roles Management')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Roles Management" description="Roles of your company. Changes apply to your company only.">
            @can('roles.create')
                <x-slot:actions>
                    <x-ui.button icon="plus" :href="route('admin.roles.create')">Add New Role</x-ui.button>
                </x-slot:actions>
            @endcan
        </x-ui.page-header>

        <x-ui.flash />

        @if ($roles->count() > 0)
            <x-ui.card flush class="py-0!">
                <x-ui.table>
                    <thead>
                        <tr><th>ID</th><th>Role Name</th><th>Permissions</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            @php($canManage = in_array($role->id, $manageable))
                            <tr>
                                <td data-label="ID" class="text-muted-foreground tabular-nums max-md:hidden!">#{{ $role->id }}</td>
                                <td data-label="Role Name" data-primary>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-foreground">{{ $role->name }}</span>
                                        @if ($role->isLocked())
                                            <x-ui.badge tone="neutral">{{ svg('lucide-lock', '', ['aria-hidden' => 'true']) }} Locked</x-ui.badge>
                                        @elseif ($role->is_system)
                                            <x-ui.badge tone="brand">System</x-ui.badge>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Permissions" class="whitespace-normal">
                                    @if ($role->permissions && $role->permissions->count() > 0)
                                        <details class="group max-w-2xl">
                                            <summary class="inline-flex cursor-pointer list-none items-center gap-1 text-sm font-medium text-foreground hover:underline [&::-webkit-details-marker]:hidden">
                                                {{ svg('lucide-chevron-right', 'size-4 transition-transform group-open:rotate-90', ['aria-hidden' => 'true']) }}
                                                {{ $role->permissions->count() }} {{ Str::plural('permission', $role->permissions->count()) }}
                                            </summary>
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                @foreach ($role->permissions as $permission)
                                                    <x-ui.badge variant="outline" class="font-mono text-[11px]">{{ $permission->name }}</x-ui.badge>
                                                @endforeach
                                            </div>
                                        </details>
                                    @else
                                        <span class="text-muted-foreground">No permissions</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        @if (auth()->user()->can('roles.edit') && $canManage)
                                            <x-ui.button variant="ghost" size="icon-sm" icon="pencil" :href="route('admin.roles.edit', $role->id)" :aria-label="'Edit '.$role->name" title="Edit" />
                                        @endif
                                        @if (auth()->user()->can('roles.delete') && $canManage && ! ($role->is_system && $role->in_use))
                                            <form method="POST" action="{{ route('admin.roles.destroy', $role->id) }}"
                                                data-confirm="Delete the {{ $role->name }} role?" data-confirm-button="Delete"
                                                data-confirm-text="This action cannot be undone.">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.button type="submit" variant="ghost" size="icon-sm" icon="trash-2" class="text-destructive" :aria-label="'Delete '.$role->name" title="Delete" />
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        @else
            <x-ui.empty-state icon="shield-user" title="No roles found" description="Get started by creating your first role.">
                @can('roles.create')
                    <x-ui.button icon="plus" :href="route('admin.roles.create')">Create Role</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @endif
    </div>
@endsection

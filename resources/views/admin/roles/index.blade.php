@extends('layouts.main-layout')

@section('title', 'Roles and permissions')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Roles and permissions" description="What each role in your company can do. Changes apply to your company only.">
            @if ($customRoles)
                @can('roles.create')
                    <x-slot:actions>
                        <x-ui.button icon="plus" :href="route('admin.roles.create')">New role</x-ui.button>
                    </x-slot:actions>
                @endcan
            @endif
        </x-ui.page-header>

        <x-ui.flash />

        <x-ui.alert class="mb-6" title="How roles work">
            Owner and Driver are locked. Administrator, Dispatcher and Office / Compliance can be edited; an edited role keeps
            your changes and can be reset to its defaults.
            @if ($customRoles)
                You can also add your own roles.
            @else
                Your own custom roles need a plan with the Custom roles feature.
            @endif
        </x-ui.alert>

        <x-ui.card flush class="py-0!">
            <x-ui.table>
                <thead>
                    <tr><th>Role</th><th>Type</th><th>Permissions</th><th>Users</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        @php($canManage = in_array($role->id, $manageable))
                        <tr>
                            <td data-label="Role" data-primary>
                                <a href="{{ route($canManage && auth()->user()->can('roles.edit') ? 'admin.roles.edit' : 'admin.roles.show', $role->id) }}"
                                    class="font-medium text-foreground underline-offset-2 hover:underline">{{ $role->name }}</a>
                            </td>
                            <td data-label="Type">
                                <div class="flex flex-wrap gap-1.5">
                                    @if ($role->isLocked())
                                        <x-ui.badge tone="neutral">{{ svg('lucide-lock', '', ['aria-hidden' => 'true']) }} Locked</x-ui.badge>
                                    @elseif ($role->is_system)
                                        <x-ui.badge tone="brand">Built-in</x-ui.badge>
                                        @if ($role->customized_at)
                                            <x-ui.badge tone="warning">Edited</x-ui.badge>
                                        @endif
                                    @else
                                        <x-ui.badge tone="info">Custom</x-ui.badge>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Permissions" class="tabular-nums">{{ $role->name === \App\Authorization\RoleTemplates::OWNER ? 'All' : $role->permissions->count().' of '.$total }}</td>
                            <td data-label="Users" class="tabular-nums">{{ $role->users_count }}</td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1">
                                    @if (auth()->user()->can('roles.edit') && $canManage)
                                        <x-ui.button variant="ghost" size="icon-sm" icon="pencil" :href="route('admin.roles.edit', $role->id)" :aria-label="'Edit '.$role->name" title="Edit" />
                                    @else
                                        <x-ui.button variant="ghost" size="icon-sm" icon="eye" :href="route('admin.roles.show', $role->id)" :aria-label="'View '.$role->name" title="View" />
                                    @endif
                                    @if ($customRoles && auth()->user()->can('roles.delete') && $canManage && ! ($role->is_system && $role->in_use))
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
    </div>
@endsection

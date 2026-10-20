@extends('layouts.platform')

@section('title', 'Roles and permissions')

@section('content')
    <x-ui.page-header title="Roles and permissions" description="What each platform role can do. Assign roles to people on the Staff page.">
        <x-slot:actions>
            <x-ui.button variant="outline" icon="user-cog" :href="route('platform.staff.index')">Staff</x-ui.button>
            <x-ui.button icon="plus" :href="route('platform.roles.create')">New role</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.alert class="mb-6" title="How platform roles work">
        Platform Owner always has every permission and can't be changed. The other built-in roles can be edited;
        an edited one keeps your changes and can be reset to its defaults at any time. Custom roles are yours to add, edit and delete.
    </x-ui.alert>

    <x-ui.card flush class="py-0!">
        <x-ui.table>
            <thead>
                <tr><th>Role</th><th>Type</th><th>Permissions</th><th>Staff</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    @php($isOwner = $role->system_key === \App\Authorization\RoleTemplates::PLATFORM_OWNER)
                    <tr>
                        <td data-label="Role" data-primary class="max-w-md whitespace-normal">
                            @include('platform.partials.cell', ['title' => $role->name, 'href' => route('platform.roles.edit', $role->id), 'sub' => $role->description])
                        </td>
                        <td data-label="Type">
                            <div class="flex flex-wrap gap-1.5">
                                @if ($role->is_system)
                                    <span class="badge" data-tone="brand">Built-in</span>
                                    @if ($isOwner)
                                        <span class="badge" data-tone="neutral">{{ svg('lucide-lock', 'size-3', ['aria-hidden' => 'true']) }} Locked</span>
                                    @elseif ($role->customized_at)
                                        <span class="badge" data-tone="warning">Edited</span>
                                    @endif
                                @else
                                    <span class="badge" data-tone="info">Custom</span>
                                @endif
                            </div>
                        </td>
                        <td data-label="Permissions" class="tabular-nums">{{ $isOwner ? 'All' : $role->permissions_count.' of '.$total }}</td>
                        <td data-label="Staff" class="tabular-nums">{{ $role->staff_count }}</td>
                        <td class="text-right">
                            <x-ui.button variant="ghost" size="sm" :icon="$isOwner ? 'eye' : 'pencil'" :href="route('platform.roles.edit', $role->id)">{{ $isOwner ? 'View' : 'Edit' }}</x-ui.button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    </x-ui.card>
@endsection

@extends('layouts.main-layout')

@section('title', 'Users')

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Users" description="Manage your company's users and their roles.">
            @can('users.create')
                <x-slot:actions>
                    <x-ui.button icon="user-plus" :href="route('users.invite')">Invite User</x-ui.button>
                </x-slot:actions>
            @endcan
        </x-ui.page-header>

        <x-ui.flash />

        <x-ui.card class="mb-6">
            <form action="{{ route('users.index') }}" method="GET" class="grid gap-4 md:grid-cols-[1fr_12rem_12rem_auto] md:items-end">
                <x-ui.field label="Search" for="search" class="min-w-0">
                    <x-ui.input name="search" id="search" type="search" :value="request('search')" placeholder="Name or email" />
                </x-ui.field>
                <x-ui.field label="Role" for="role">
                    <x-ui.select name="role" id="role" :options="$roles->pluck('name', 'id')" :value="request('role')" placeholder="All Roles" />
                </x-ui.field>
                <x-ui.field label="Status" for="status">
                    <x-ui.select name="status" id="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="request('status')" placeholder="All Status" />
                </x-ui.field>
                <div class="flex gap-2">
                    <x-ui.button type="submit" variant="secondary" icon="list-filter" class="max-md:flex-1">Apply Filters</x-ui.button>
                    @if (request()->anyFilled(['search', 'role', 'status']))
                        <x-ui.button variant="ghost" :href="route('users.index')">Clear</x-ui.button>
                    @endif
                </div>
            </form>
        </x-ui.card>

        @if ($users->count() > 0)
            <x-ui.card flush class="py-0!">
                <x-ui.table>
                    <thead>
                        <tr><th>User</th><th>Roles</th><th>Status</th><th>Joined</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $listUser)
                            @php($active = $listUser->status === 'active')
                            <tr>
                                <td data-label="User" data-primary>
                                    <div class="flex items-center gap-3">
                                        <x-ui.avatar :name="$listUser->name" />
                                        @include('platform.partials.cell', ['title' => $listUser->name, 'sub' => $listUser->email])
                                    </div>
                                </td>
                                <td data-label="Roles">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($listUser->roles as $role)
                                            <x-ui.badge variant="outline">{{ $role->name }}</x-ui.badge>
                                        @empty
                                            <x-ui.badge tone="neutral">No roles</x-ui.badge>
                                        @endforelse
                                        @if ($listUser->permissions_count > 0)
                                            <x-ui.badge tone="info" :title="$listUser->permissions_count.' '.Str::plural('permission', $listUser->permissions_count).' granted directly'">+{{ $listUser->permissions_count }} extra</x-ui.badge>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Status">@include('partials.ui.status-badge', ['status' => $listUser->status === 'inactive' ? 'inactive' : 'active'])</td>
                                <td data-label="Joined" class="whitespace-nowrap text-muted-foreground">{{ $listUser->created_at->format('M d, Y') }}</td>
                                <td class="text-right">
                                    @if ($listUser->id !== auth()->id() && $listUser->id !== $ownerId)
                                        <div class="flex justify-end gap-1">
                                            @can('users.edit')
                                                <x-ui.button variant="ghost" size="icon-sm" icon="pencil" :href="route('users.edit', $listUser->id)" :aria-label="'Edit '.$listUser->name" title="Edit User" />
                                            @endcan
                                            @can('users.deactivate')
                                                <form action="{{ route($active ? 'users.deactivate' : 'users.reactivate', $listUser->id) }}" method="POST"
                                                    @if ($active)
                                                        data-confirm="Deactivate {{ $listUser->name }}?" data-confirm-button="Deactivate"
                                                        data-confirm-text="They can't sign in until you reactivate them."
                                                    @endif>
                                                    @csrf
                                                    <x-ui.button type="submit" variant="ghost" size="sm" :class="$active ? 'text-destructive' : ''">{{ $active ? 'Deactivate' : 'Reactivate' }}</x-ui.button>
                                                </form>
                                            @endcan
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
                @if ($users->hasPages())
                    <div class="border-t px-6 py-4">{{ $users->links() }}</div>
                @endif
            </x-ui.card>
        @else
            <x-ui.empty-state icon="users" title="No users found"
                :description="request()->anyFilled(['search', 'role', 'status']) ? 'No user matches these filters.' : 'Invite your team to get started.'">
                @can('users.create')
                    <x-ui.button icon="user-plus" :href="route('users.invite')">Invite User</x-ui.button>
                @endcan
            </x-ui.empty-state>
        @endif

        @if ($invitations->isNotEmpty())
            <x-ui.card flush title="Pending invitations" class="mt-6 pb-0!">
                <x-ui.table>
                    <thead>
                        <tr><th>Email</th><th>Role</th><th>Expires</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($invitations as $invitation)
                            <tr>
                                <td data-label="Email" data-primary class="font-medium">{{ $invitation->email }}</td>
                                <td data-label="Role">{{ $invitation->role->name }}</td>
                                <td data-label="Expires">
                                    @if ($invitation->expires_at->isPast())
                                        <x-ui.badge tone="danger">Expired</x-ui.badge>
                                    @else
                                        <span class="text-muted-foreground">{{ $invitation->expires_at->format('M d, Y') }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <form action="{{ route('users.invitations.resend', $invitation->id) }}" method="POST">
                                            @csrf
                                            <x-ui.button type="submit" variant="ghost" size="sm" icon="send">Resend</x-ui.button>
                                        </form>
                                        <form action="{{ route('users.invitations.destroy', $invitation->id) }}" method="POST"
                                            data-confirm="Revoke this invitation?" data-confirm-button="Revoke"
                                            data-confirm-text="The link in the email to {{ $invitation->email }} stops working.">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" variant="ghost" size="sm" class="text-destructive">Revoke</x-ui.button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        @endif
    </div>
@endsection

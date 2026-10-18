@extends('layouts.platform')

@section('title', 'Staff')

@section('content')
    <x-ui.page-header title="Platform staff" description="At least two active Platform Owners must remain at all times.">
        <x-slot:actions>
            <x-ui.button icon="user-plus" :href="route('platform.staff.create')">Invite staff</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card flush class="py-0!">
        <x-ui.table>
            <thead>
                <tr><th>Name</th><th>Roles</th><th>Status</th><th>Last login</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody>
                @foreach ($staff as $member)
                    <tr>
                        <td data-label="Name" data-primary>
                            @include('platform.partials.cell', ['title' => $member->name, 'sub' => $member->email])
                        </td>
                        <td data-label="Roles">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($member->roles as $role)
                                    <span class="badge" data-variant="outline">{{ $role->name }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td data-label="Status">@include('partials.ui.status-badge', ['status' => $member->status])</td>
                        <td data-label="Last login" class="text-muted-foreground">{{ $member->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="text-right">
                            @if ($member->is(auth('platform')->user()))
                                <span class="text-xs text-muted-foreground">You</span>
                            @else
                                <div class="flex justify-end gap-1">
                                    <x-ui.button variant="ghost" size="icon-sm" icon="pencil" :href="route('platform.staff.edit', $member)" aria-label="Edit {{ $member->name }}" title="Edit" />
                                    <form method="POST" action="{{ route('platform.staff.status', $member) }}"
                                        @if ($member->status === 'active')
                                            data-confirm="Disable {{ $member->name }}?" data-confirm-button="Disable"
                                            data-confirm-text="They can't sign in to the platform until enabled again."
                                        @endif>
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $member->status === 'active' ? 'inactive' : 'active' }}">
                                        <x-ui.button type="submit" variant="ghost" size="sm">{{ $member->status === 'active' ? 'Disable' : 'Enable' }}</x-ui.button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    </x-ui.card>
@endsection

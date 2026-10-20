@extends('layouts.platform')

@section('title', $member->exists ? 'Edit staff' : 'Invite staff')

@php
    $breadcrumbs = [['label' => 'Staff', 'href' => route('platform.staff.index')], ['label' => $member->exists ? $member->name : 'Invite staff']];
    // roles[] is an array, so it is read here and not by x-ui.checkbox (which compares one value)
    $checked = array_map('intval', (array) old('roles', $held));
@endphp

@section('content')
    <x-ui.page-header :title="$member->exists ? 'Edit '.$member->name : 'Invite staff'"
        description="Platform staff sign in at /platform. Their roles decide which screens they can open.">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="$breadcrumbs" />
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <form method="POST" action="{{ $member->exists ? route('platform.staff.update', $member) : route('platform.staff.store') }}" class="max-w-2xl space-y-6">
        @csrf
        @if ($member->exists)
            @method('PUT')
        @endif

        <x-ui.card title="Details" :description="$member->exists ? null : 'They are emailed a link to set their password. The email can\'t already be a tenant or platform login.'">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Name" name="name" required>
                    <x-ui.input name="name" :value="$member->name" required maxlength="255" />
                </x-ui.field>
                <x-ui.field label="Email" name="email" required>
                    <x-ui.input type="email" name="email" :value="$member->email" required maxlength="255" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Roles" description="At least one. At least two active Platform Owners must remain at all times.">
            <x-slot:action>
                <x-ui.button variant="outline" size="sm" icon="shield-check" :href="route('platform.roles.index')">Manage roles</x-ui.button>
            </x-slot:action>
            <fieldset id="f-roles" class="grid gap-3 sm:grid-cols-2" @error('roles') data-invalid="true" aria-describedby="f-roles-error" @enderror>
                <legend class="sr-only">Roles</legend>
                @foreach ($roles as $role)
                    <label for="role-{{ $role->id }}" class="label flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 font-normal has-checked:border-primary has-checked:bg-primary/5">
                        <input type="checkbox" name="roles[]" id="role-{{ $role->id }}" value="{{ $role->id }}" class="input mt-0.5" @checked(in_array($role->id, $checked, true))>
                        <span class="grid gap-1 leading-snug">
                            <span class="font-medium text-foreground">{{ $role->name }}</span>
                            @if ($role->description)
                                <span class="text-sm text-muted-foreground">{{ $role->description }}</span>
                            @endif
                            <span class="text-xs text-muted-foreground">
                                {{ $role->system_key === \App\Authorization\RoleTemplates::PLATFORM_OWNER
                                    ? 'Every permission'
                                    : ($role->permissions->isEmpty() ? 'No permissions' : $role->permissions->map(fn ($permission) => \App\Authorization\PermissionCatalogue::PLATFORM_DETAILS[$permission->name][1] ?? $permission->name)->join(', ')) }}
                            </span>
                        </span>
                    </label>
                @endforeach
                @error('roles')
                    <p role="alert" id="f-roles-error" class="text-sm text-destructive">{{ $message }}</p>
                @enderror
            </fieldset>
        </x-ui.card>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button variant="outline" :href="route('platform.staff.index')">Cancel</x-ui.button>
            <x-ui.button type="submit" icon="check">{{ $member->exists ? 'Save changes' : 'Send invite' }}</x-ui.button>
        </div>
    </form>
@endsection

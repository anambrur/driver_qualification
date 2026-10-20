@extends('layouts.main-layout')

@section('title', $role ? 'Edit role' : 'New role')

@php
    $checked = old('permissions', $role?->permissions->pluck('name')->all() ?? []);
@endphp

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header :title="$role ? $role->name : 'New role'" description="Tick what people with this role may do. You can only grant permissions you hold yourself.">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Roles', 'href' => route('admin.roles.index')], ['label' => $role ? $role->name : 'New role']]" />
            </x-slot:breadcrumbs>
        </x-ui.page-header>

        <x-ui.flash />

        <div class="max-w-5xl space-y-6">
            @if ($role?->is_system && $role->customized_at)
                <x-ui.alert tone="warning" title="Edited built-in role">
                    These permissions differ from the defaults since {{ $role->customized_at->format('M j, Y') }}. New permissions added to the app later won't reach this role until you tick them or reset it.
                </x-ui.alert>
            @endif

            <form action="{{ $role ? route('admin.roles.update', $role->id) : route('admin.roles.store') }}" method="POST" class="space-y-6">
                @csrf
                @if ($role)
                    @method('PUT')
                @endif

                <x-ui.card title="Role">
                    <x-ui.field label="Name" for="name" name="name" required :hint="$role?->is_system ? 'Built-in roles keep their name.' : null" class="max-w-md">
                        <x-ui.input name="name" id="name" required placeholder="e.g. Safety Manager" :value="$role?->name" :readonly="(bool) $role?->is_system" />
                    </x-ui.field>
                </x-ui.card>

                <x-ui.card title="Permissions" :description="$role ? 'People with this role get changes on their next page load. Only your company is affected.' : 'Only your company is affected.'">
                    @error('permissions.*')
                        <x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>
                    @enderror

                    @if (count($groups) === 0)
                        <x-ui.empty-state icon="key-round" title="No permissions available" />
                    @else
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($groups as $group => $permissions)
                                <fieldset x-data class="overflow-hidden rounded-lg border border-border">
                                    <legend class="sr-only">{{ $group }}</legend>
                                    <div class="flex items-center justify-between gap-3 border-b border-border bg-muted/60 px-4 py-2.5">
                                        <h3 class="text-sm font-semibold">{{ $group }}</h3>
                                        <div class="flex gap-1">
                                            <button type="button" class="btn" data-variant="ghost" data-size="xs"
                                                x-on:click="$root.querySelectorAll('input[name=\'permissions[]\']').forEach((box) => box.checked = true)">All</button>
                                            <button type="button" class="btn" data-variant="ghost" data-size="xs"
                                                x-on:click="$root.querySelectorAll('input[name=\'permissions[]\']').forEach((box) => box.checked = false)">None</button>
                                        </div>
                                    </div>
                                    <div class="grid gap-2.5 p-4">
                                        @foreach ($permissions as $permission => $label)
                                            <label for="perm-{{ Str::slug($permission) }}" class="label flex cursor-pointer items-center gap-3 font-normal" title="{{ $permission }}">
                                                <input type="checkbox" name="permissions[]" id="perm-{{ Str::slug($permission) }}" value="{{ $permission }}" class="input"
                                                    @checked(in_array($permission, $checked, true))>
                                                <span class="flex flex-wrap items-center gap-2">
                                                    {{ $label }}
                                                    @if ($defaults !== null && in_array($permission, $defaults, true) !== in_array($permission, $checked, true))
                                                        <span class="badge" data-tone="warning">{{ in_array($permission, $defaults, true) ? 'Default: on' : 'Default: off' }}</span>
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                    @endif

                    <x-slot:footer class="justify-end">
                        <x-ui.button variant="outline" :href="route('admin.roles.index')">Cancel</x-ui.button>
                        <x-ui.button type="submit" icon="check">{{ $role ? 'Save role' : 'Create role' }}</x-ui.button>
                    </x-slot:footer>
                </x-ui.card>
            </form>

            @if ($role?->is_system && $role->customized_at)
                <x-ui.card title="Reset to defaults" description="Puts back the built-in permissions. From then on the role follows the app defaults again.">
                    <form method="POST" action="{{ route('admin.roles.reset', $role->id) }}"
                        data-confirm="Reset {{ $role->name }}?" data-confirm-button="Reset" data-confirm-text="Your changes to this role's permissions are replaced by the defaults.">
                        @csrf
                        <x-ui.button type="submit" variant="outline" icon="rotate-ccw">Reset to defaults</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection

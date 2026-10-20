@extends('layouts.platform')

@section('title', $role ? $role->name : 'New role')

@php
    $checked = old('permissions', $held);
    $defaults ??= null;
@endphp

@section('content')
    <x-ui.page-header :title="$role ? $role->name : 'New role'"
        :description="$locked ? 'Platform Owner always has every permission. It is shown here for reference.' : 'Tick what people with this role may do. You can only grant permissions you hold yourself.'">
        <x-slot:breadcrumbs>
            <x-ui.breadcrumb :items="[['label' => 'Roles and permissions', 'href' => route('platform.roles.index')], ['label' => $role ? $role->name : 'New role']]" />
        </x-slot:breadcrumbs>
    </x-ui.page-header>

    <div class="max-w-4xl space-y-6">
        @if ($locked)
            <x-ui.alert icon="lock" title="Locked role">
                Every permission, now and in future, belongs to Platform Owner. At least two active Platform Owners must exist.
            </x-ui.alert>
        @elseif ($role?->is_system && $role->customized_at)
            <x-ui.alert tone="warning" title="Edited built-in role">
                These permissions differ from the defaults since {{ $role->customized_at->format('M j, Y') }}. New permissions added to the platform later won't reach this role until you tick them or reset it.
            </x-ui.alert>
        @endif

        <form method="POST" action="{{ $role ? route('platform.roles.update', $role->id) : route('platform.roles.store') }}" class="space-y-6">
            @csrf
            @if ($role)
                @method('PUT')
            @endif

            <x-ui.card title="Role">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Name" name="name" required :hint="$role?->is_system ? 'Built-in roles keep their name.' : null">
                        <x-ui.input name="name" :value="$role?->name" required maxlength="100" placeholder="e.g. Content Editor" :readonly="(bool) $role?->is_system" :disabled="$locked" />
                    </x-ui.field>
                    <x-ui.field label="Description" name="description" hint="Optional. Shown on the staff form.">
                        <x-ui.input name="description" :value="$role?->description" maxlength="255" placeholder="What this role is for" :disabled="$locked" />
                    </x-ui.field>
                </div>
            </x-ui.card>

            <x-ui.card title="Permissions" :description="$role && ! $locked ? 'Staff with this role get changes on their next page load.' : null">
                @error('permissions')
                    <x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>
                @enderror
                @error('permissions.*')
                    <x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>
                @enderror

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($groups as $group => $permissions)
                        <fieldset x-data class="overflow-hidden rounded-lg border border-border">
                            <legend class="sr-only">{{ $group }}</legend>
                            <div class="flex items-center justify-between gap-3 border-b border-border bg-muted/60 px-4 py-2.5">
                                <h3 class="text-sm font-semibold">{{ $group }}</h3>
                                @unless ($locked)
                                    <div class="flex gap-1">
                                        <button type="button" class="btn" data-variant="ghost" data-size="xs"
                                            x-on:click="$root.querySelectorAll('input[name=\'permissions[]\']').forEach((box) => box.checked = true)">All</button>
                                        <button type="button" class="btn" data-variant="ghost" data-size="xs"
                                            x-on:click="$root.querySelectorAll('input[name=\'permissions[]\']').forEach((box) => box.checked = false)">None</button>
                                    </div>
                                @endunless
                            </div>
                            <div class="grid gap-3 p-4">
                                @foreach ($permissions as $name => [$label, $description])
                                    <label for="perm-{{ Str::slug($name) }}" class="label flex cursor-pointer items-start gap-3 font-normal">
                                        <input type="checkbox" name="permissions[]" id="perm-{{ Str::slug($name) }}" value="{{ $name }}" class="input mt-0.5"
                                            @checked($locked || in_array($name, $checked, true)) @disabled($locked)>
                                        <span class="grid gap-0.5 leading-snug">
                                            <span class="flex flex-wrap items-center gap-2 font-medium text-foreground">
                                                {{ $label }}
                                                @if ($defaults !== null && ! $locked && in_array($name, $defaults, true) !== in_array($name, $checked, true))
                                                    <span class="badge" data-tone="warning">{{ in_array($name, $defaults, true) ? 'Default: on' : 'Default: off' }}</span>
                                                @endif
                                            </span>
                                            <span class="text-sm text-muted-foreground">{{ $description }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>

                @unless ($locked)
                    <x-slot:footer class="justify-end">
                        <x-ui.button variant="outline" :href="route('platform.roles.index')">Back</x-ui.button>
                        <x-ui.button type="submit" icon="check">{{ $role ? 'Save role' : 'Create role' }}</x-ui.button>
                    </x-slot:footer>
                @endunless
            </x-ui.card>
        </form>

        @if ($role?->is_system && ! $locked && $role->customized_at)
            <x-ui.card title="Reset to defaults" description="Puts back the built-in permissions. From then on the role follows the platform defaults again.">
                <form method="POST" action="{{ route('platform.roles.reset', $role->id) }}"
                    data-confirm="Reset {{ $role->name }}?" data-confirm-button="Reset" data-confirm-text="Your changes to this role's permissions are replaced by the defaults.">
                    @csrf
                    <x-ui.button type="submit" variant="outline" icon="rotate-ccw">Reset to defaults</x-ui.button>
                </form>
            </x-ui.card>
        @endif

        @if ($role && ! $role->is_system)
            <x-ui.card title="Delete role" :description="$staffCount > 0 ? 'Held by '.$staffCount.' staff member(s). Remove it from them on the Staff page first.' : 'Nobody holds this role. Deleting it can\'t be undone.'">
                @error('role')
                    <x-ui.alert tone="danger" class="mb-4">{{ $message }}</x-ui.alert>
                @enderror
                <form method="POST" action="{{ route('platform.roles.destroy', $role->id) }}"
                    data-confirm="Delete {{ $role->name }}?" data-confirm-button="Delete role" data-confirm-text="The role is removed. This can't be undone.">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="destructive" icon="trash-2" :disabled="$staffCount > 0">Delete role</x-ui.button>
                </form>
            </x-ui.card>
        @endif
    </div>
@endsection

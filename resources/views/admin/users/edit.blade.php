@extends('layouts.main-layout')

@section('title', 'Edit User')

@php
    $heldIds = old('roles', $user->roles->pluck('id')->all());
    $fixedRoles = $user->roles->whereNotIn('id', $roles->modelKeys());
@endphp

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header title="Edit User" :description="$user->email">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Users', 'href' => route('users.index')], ['label' => $user->name]]" />
            </x-slot:breadcrumbs>
        </x-ui.page-header>

        <div class="max-w-3xl space-y-6">
            <form action="{{ route('users.update', $user->id) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <x-ui.card title="Details">
                    <x-ui.field label="Name" for="name" name="name" required class="max-w-md">
                        <x-ui.input name="name" id="name" :value="$user->name" required />
                    </x-ui.field>
                </x-ui.card>

                <x-ui.card title="Roles" description="The roles decide which screens this user can open.">
                    <fieldset id="f-roles" class="grid gap-4" @error('roles') data-invalid="true" aria-describedby="f-roles-error" @enderror>
                        <legend class="sr-only">Roles</legend>
                        @if ($fixedRoles->isNotEmpty())
                            <p class="flex flex-wrap items-center gap-1 text-sm text-muted-foreground">
                                Also holds (you can't change these):
                                @foreach ($fixedRoles as $locked)
                                    <x-ui.badge tone="neutral">{{ $locked->name }}</x-ui.badge>
                                @endforeach
                            </p>
                        @endif

                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @forelse ($roles as $role)
                                <label for="role-{{ $role->id }}" class="label cursor-pointer items-start gap-3 rounded-lg border p-3 font-normal transition-colors hover:bg-muted/50 has-checked:border-primary has-checked:bg-primary/5">
                                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" id="role-{{ $role->id }}" class="input mt-0.5" @checked(in_array($role->id, $heldIds))>
                                    <span class="font-medium text-foreground">{{ $role->name }}</span>
                                </label>
                            @empty
                                <p class="text-sm text-muted-foreground">There are no roles you can assign.</p>
                            @endforelse
                        </div>
                        @error('roles')
                            <p role="alert" id="f-roles-error" class="text-sm text-destructive">{{ $message }}</p>
                        @enderror
                    </fieldset>
                </x-ui.card>

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <x-ui.button variant="outline" :href="route('users.index')">Cancel</x-ui.button>
                    <x-ui.button type="submit" icon="check">Update User</x-ui.button>
                </div>
            </form>

            @if ($showUpgradeHint && ! $showGrants)
                <p class="text-sm text-muted-foreground">
                    Extra permissions per user come with the Custom roles feature.
                    <a href="{{ route('billing.index') }}" class="font-medium text-primary underline-offset-4 hover:underline">See plans</a>
                </p>
            @endif

            @if ($showGrants)
                @php
                    $viaRoles = [];
                    foreach ($user->roles as $heldRole) {
                        foreach ($heldRole->permissions as $permission) {
                            $viaRoles[$permission->name][] = $heldRole->name;
                        }
                    }
                    ksort($viaRoles);
                    $direct = $user->permissions->pluck('name')->sort()->values();
                @endphp
                <x-ui.card title="Extra permissions" description="Permissions this user gets on top of their roles. Changes here are saved straight away.">
                    <div class="grid gap-6">
                        <details>
                            <summary class="cursor-pointer text-sm font-medium">Through roles ({{ count($viaRoles) }})</summary>
                            @if ($viaRoles === [])
                                <p class="mt-3 text-sm text-muted-foreground">No permissions through roles.</p>
                            @else
                                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                    @foreach (\App\Authorization\PermissionCatalogue::tenantGroups(array_keys($viaRoles)) as $group => $permissions)
                                        <div>
                                            <h3 class="text-sm font-semibold">{{ $group }}</h3>
                                            <ul class="mt-1 grid gap-1 text-sm">
                                                @foreach ($permissions as $permission => $label)
                                                    <li title="{{ $permission }}">{{ $label }} <span class="text-muted-foreground">· {{ implode(', ', $viaRoles[$permission]) }}</span></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </details>

                        <div>
                            <h3 class="text-sm font-semibold">Direct grants</h3>
                            @if ($direct->isEmpty())
                                <p class="mt-1 text-sm text-muted-foreground">No extra permissions.</p>
                            @else
                                <ul class="mt-2 divide-y divide-border rounded-lg border border-border">
                                    @foreach (\App\Authorization\PermissionCatalogue::tenantGroups($direct) as $group => $permissions)
                                        @foreach ($permissions as $permission => $label)
                                            <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                                                <span title="{{ $permission }}">{{ $label }} <span class="text-muted-foreground">· {{ $group }}</span></span>
                                                @if (in_array($permission, $grantable, true))
                                                    <form method="POST" action="{{ route('users.permissions.destroy', [$user->id, $permission]) }}"
                                                        data-confirm="Revoke {{ $group }}: {{ $label }}?" data-confirm-button="Revoke" data-confirm-text="{{ $user->name }} loses this permission on their next page load.">
                                                        @csrf
                                                        @method('DELETE')
                                                        <x-ui.button type="submit" variant="ghost" size="sm" class="text-destructive">Revoke</x-ui.button>
                                                    </form>
                                                @else
                                                    <span class="text-muted-foreground">Only someone who holds this can revoke it</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        @if (! $canGrant)
                            <p class="text-sm text-muted-foreground">
                                Custom roles isn't active on your plan, so you can revoke these extra permissions but not add new ones.
                                @if ($showUpgradeHint)
                                    <a href="{{ route('billing.index') }}" class="font-medium text-primary underline-offset-4 hover:underline">See plans</a>
                                @endif
                            </p>
                        @elseif ($grantGroups === [])
                            <p class="text-sm text-muted-foreground">There are no more permissions you can grant to this user.</p>
                        @else
                            <form method="POST" action="{{ route('users.permissions.store', $user->id) }}" class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                @csrf
                                <x-ui.field label="Add a permission" for="permission" name="permission" class="flex-1">
                                    <x-ui.select name="permission" id="permission" placeholder="Choose a permission" required>
                                        @foreach ($grantGroups as $group => $permissions)
                                            <optgroup label="{{ $group }}">
                                                @foreach ($permissions as $permission => $label)
                                                    <option value="{{ $permission }}" @selected(old('permission') === $permission)>{{ $label }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </x-ui.select>
                                </x-ui.field>
                                <x-ui.button type="submit" icon="plus">Add</x-ui.button>
                            </form>
                        @endif
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection

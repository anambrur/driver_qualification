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

        <form action="{{ route('users.update', $user->id) }}" method="POST" class="max-w-3xl space-y-6">
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
    </div>
@endsection

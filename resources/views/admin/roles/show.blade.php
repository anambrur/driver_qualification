@extends('layouts.main-layout')

@section('title', $role->name)

@section('content')
    <div class="p-4 sm:p-6 lg:p-8">
        <x-ui.page-header :title="$role->name" description="What people with this role can do.">
            <x-slot:breadcrumbs>
                <x-ui.breadcrumb :items="[['label' => 'Roles', 'href' => route('admin.roles.index')], ['label' => $role->name]]" />
            </x-slot:breadcrumbs>
        </x-ui.page-header>

        <div class="max-w-5xl space-y-6">
            <x-ui.alert icon="lock" :title="$role->isLocked() ? 'Locked role' : 'View only'">
                @if ($role->name === \App\Authorization\RoleTemplates::OWNER)
                    Owner always has every permission. Ownership moves only through a transfer.
                @elseif ($role->isLocked())
                    This role is managed by the app and can't be changed.
                @else
                    You can only edit roles whose permissions you hold yourself.
                @endif
            </x-ui.alert>

            <x-ui.card title="Permissions">
                @if (count($groups) === 0)
                    <x-ui.empty-state icon="key-round" title="No permissions" class="border-0" />
                @else
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($groups as $group => $permissions)
                            <section class="overflow-hidden rounded-lg border border-border">
                                <h3 class="border-b border-border bg-muted/60 px-4 py-2.5 text-sm font-semibold">{{ $group }}</h3>
                                <ul class="grid gap-2 p-4 text-sm">
                                    @foreach ($permissions as $permission => $label)
                                        <li class="flex items-center gap-2" title="{{ $permission }}">
                                            {{ svg('lucide-check', 'size-4 text-success', ['aria-hidden' => 'true']) }} {{ $label }}
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
@endsection

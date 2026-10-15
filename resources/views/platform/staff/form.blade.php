@extends('layouts.platform')

@section('title', $member->exists ? 'Edit staff' : 'Invite staff')

@php($input = 'mt-1 h-10 w-full rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90')

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-800 dark:text-white/90">{{ $member->exists ? 'Edit '.$member->name : 'Invite staff' }}</h1>

    <form method="POST" action="{{ $member->exists ? route('platform.staff.update', $member) : route('platform.staff.store') }}"
        class="max-w-xl space-y-4 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        @csrf
        @if ($member->exists)
            @method('PUT')
        @endif
        <label class="block text-sm text-gray-700 dark:text-gray-300">Name<input name="name" value="{{ old('name', $member->name) }}" class="{{ $input }}" required></label>
        <label class="block text-sm text-gray-700 dark:text-gray-300">Email<input type="email" name="email" value="{{ old('email', $member->email) }}" class="{{ $input }}" required></label>
        <fieldset>
            <legend class="text-sm text-gray-700 dark:text-gray-300">Roles</legend>
            @foreach ($roles as $role)
                <label class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $held)))> {{ $role->name }}
                </label>
            @endforeach
        </fieldset>
        @unless ($member->exists)
            <p class="text-xs text-gray-500">They are emailed a link to set their password. The email can't already be a tenant or platform login.</p>
        @endunless
        <div class="flex gap-3">
            <button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Save</button>
            <a href="{{ route('platform.staff.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700 dark:text-gray-300">Cancel</a>
        </div>
    </form>
@endsection

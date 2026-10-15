@extends('layouts.platform')

@section('title', 'Staff')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-xl font-semibold text-gray-800 dark:text-white/90">Platform staff</h1>
        <a href="{{ route('platform.staff.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Invite staff</a>
    </div>
    <p class="mb-4 text-sm text-gray-500">At least two active Platform Owners must remain at all times.</p>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800">
                <tr><th class="px-4 py-3">Name</th><th class="px-4 py-3">Roles</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Last login</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($staff as $member)
                    <tr class="text-gray-700 dark:text-gray-300">
                        <td class="px-4 py-3">{{ $member->name }}<br><span class="text-xs text-gray-500">{{ $member->email }}</span></td>
                        <td class="px-4 py-3">{{ $member->roles->pluck('name')->join(', ') }}</td>
                        <td class="px-4 py-3">{{ $member->status }}</td>
                        <td class="px-4 py-3">{{ $member->last_login_at?->diffForHumans() ?? 'never' }}</td>
                        <td class="px-4 py-3">
                            @unless ($member->is(auth('platform')->user()))
                                <div class="flex gap-3">
                                    <a href="{{ route('platform.staff.edit', $member) }}" class="text-brand-600 hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('platform.staff.status', $member) }}">
                                        @csrf
                                        <input type="hidden" name="status" value="{{ $member->status === 'active' ? 'inactive' : 'active' }}">
                                        <button class="{{ $member->status === 'active' ? 'text-red-600' : 'text-green-600' }} hover:underline">{{ $member->status === 'active' ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                </div>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

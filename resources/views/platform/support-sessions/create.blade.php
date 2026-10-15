@extends('layouts.platform')

@section('title', 'Request support access')

@php($input = 'mt-1 h-10 w-full rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90')

@section('content')
    <h1 class="mb-2 text-xl font-semibold text-gray-800 dark:text-white/90">Request support access</h1>
    <p class="mb-6 max-w-2xl text-sm text-gray-500">
        The company owner is emailed your reason and must approve, and so must a second Platform Owner.
        Approval stays valid for {{ \App\Models\PlatformSupportSession::REQUEST_WINDOW_HOURS }} hours; a started session lasts at most
        {{ settings('support_session_max_minutes', 240) }} minutes.
    </p>

    <form method="POST" action="{{ route('platform.support-sessions.store') }}"
        class="max-w-2xl space-y-4 rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        @csrf
        <label class="block text-sm text-gray-700 dark:text-gray-300">Company
            <select name="company_id" class="{{ $input }}" required>
                <option value="">Choose…</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((int) old('company_id') === $company->id)>{{ $company->company_name }}</option>
                @endforeach
            </select>
        </label>
        <fieldset class="text-sm text-gray-700 dark:text-gray-300">
            <legend>Access</legend>
            <label class="mt-2 flex items-center gap-2"><input type="radio" name="scope" value="read" @checked(old('scope', 'read') === 'read')> Read only</label>
            <label class="mt-2 flex items-center gap-2"><input type="radio" name="scope" value="read_write" @checked(old('scope') === 'read_write')> Read and write (no billing, users, roles, or deleting drivers and documents)</label>
        </fieldset>
        <label class="block text-sm text-gray-700 dark:text-gray-300">Reason (the owner sees this)
            <textarea name="reason" rows="3" class="{{ $input }} h-auto py-2" required minlength="10" maxlength="1000">{{ old('reason') }}</textarea>
        </label>
        <div class="flex gap-3">
            <button class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Send request</button>
            <a href="{{ route('platform.support-sessions.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700 dark:text-gray-300">Cancel</a>
        </div>
    </form>
@endsection

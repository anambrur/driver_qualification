@extends('layouts.platform')

@section('title', 'Audit log')

@php($input = 'h-10 rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90')

@section('content')
    <h1 class="mb-6 text-xl font-semibold text-gray-800 dark:text-white/90">Audit log</h1>

    <form method="GET" class="mb-4 flex flex-wrap gap-3">
        <input type="number" name="company_id" value="{{ $filters['company_id'] ?? '' }}" placeholder="Company id" class="{{ $input }} w-32">
        <select name="actor_type" class="{{ $input }}">
            <option value="">Any actor</option>
            @foreach ($actorTypes as $type)
                <option value="{{ $type }}" @selected(($filters['actor_type'] ?? null) === $type)>{{ str_replace('_', ' ', $type) }}</option>
            @endforeach
        </select>
        <input name="action" value="{{ $filters['action'] ?? '' }}" placeholder="Action starts with" class="{{ $input }}">
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="{{ $input }}">
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="{{ $input }}">
        <button class="rounded-lg border border-gray-300 px-4 text-sm dark:border-gray-700 dark:text-gray-300">Filter</button>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-gray-500">
                <tr><th class="py-2 pr-4">When</th><th class="py-2 pr-4">Action</th><th class="py-2 pr-4">Actor</th><th class="py-2 pr-4">Company</th><th class="py-2 pr-4">Subject</th><th class="py-2 pr-4">Changes</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100 align-top dark:divide-gray-800">
                @forelse ($logs as $log)
                    <tr class="text-gray-700 dark:text-gray-300">
                        <td class="py-2 pr-4 whitespace-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="py-2 pr-4">{{ $log->action }}@if ($log->support_session_id) <span class="text-xs text-amber-600">session #{{ $log->support_session_id }}</span>@endif</td>
                        <td class="py-2 pr-4">{{ str_replace('_', ' ', $log->actor_type) }} {{ $log->actor_id }}</td>
                        <td class="py-2 pr-4">{{ $log->company_id ?? '—' }}</td>
                        <td class="py-2 pr-4">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                        <td class="py-2 pr-4"><code class="text-xs break-all">{{ $log->changes ? json_encode($log->changes, JSON_UNESCAPED_SLASHES) : '' }}</code></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-gray-500">No entries.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
@endsection

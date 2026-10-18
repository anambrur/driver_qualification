@extends('layouts.platform')

@section('title', 'Audit log')

@php
    $actorOptions = collect($actorTypes)->mapWithKeys(fn ($type) => [$type => ucfirst(str_replace('_', ' ', $type))])->all();
@endphp

@section('content')
    <x-ui.page-header title="Audit log" description="Every change on the platform and in the companies, newest first. Entries can be searched but never edited or deleted." />

    <x-ui.card class="mb-6">
        <form method="GET" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[8rem_10rem_minmax(0,1fr)_10rem_10rem_auto] lg:items-end">
            <x-ui.field label="Company id" name="company_id">
                <x-ui.input type="number" name="company_id" min="1" :value="$filters['company_id'] ?? null" />
            </x-ui.field>
            <x-ui.field label="Actor" name="actor_type">
                <x-ui.select name="actor_type" :value="$filters['actor_type'] ?? ''" :options="['' => 'Any actor'] + $actorOptions" />
            </x-ui.field>
            <x-ui.field label="Action starts with" name="action">
                <x-ui.input name="action" :value="$filters['action'] ?? null" maxlength="100" placeholder="company." class="font-mono" />
            </x-ui.field>
            <x-ui.field label="From" name="from">
                <x-ui.input type="date" name="from" :value="$filters['from'] ?? null" />
            </x-ui.field>
            <x-ui.field label="To" name="to">
                <x-ui.input type="date" name="to" :value="$filters['to'] ?? null" />
            </x-ui.field>
            <div class="flex gap-2">
                <x-ui.button type="submit" icon="filter">Filter</x-ui.button>
                @if (array_filter($filters))
                    <x-ui.button variant="ghost" :href="route('platform.audit.index')">Clear</x-ui.button>
                @endif
            </div>
        </form>
    </x-ui.card>

    <x-ui.card flush class="py-0!">
        @if ($logs->isEmpty())
            <x-ui.empty-state icon="history" title="No entries" :description="array_filter($filters) ? 'Try different filters.' : null" class="border-0" />
        @else
            <x-ui.table>
                <thead>
                    <tr><th>When</th><th>Action</th><th>Actor</th><th>Company</th><th>Subject</th><th>Changes</th></tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr class="align-top">
                            <td data-label="When" class="whitespace-nowrap tabular-nums text-muted-foreground">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td data-label="Action" data-primary>
                                <span class="font-mono text-xs font-medium">{{ $log->action }}</span>
                                @if ($log->support_session_id)
                                    <span class="badge mt-1" data-tone="warning">Session #{{ $log->support_session_id }}</span>
                                @endif
                            </td>
                            <td data-label="Actor">{{ ucfirst(str_replace('_', ' ', $log->actor_type)) }} {{ $log->actor_id }}</td>
                            <td data-label="Company">{{ $log->company_id ?? '—' }}</td>
                            <td data-label="Subject">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                            <td data-label="Changes" class="max-w-md whitespace-normal">
                                @if ($log->changes)
                                    <code class="block break-all font-mono text-xs text-muted-foreground">{{ json_encode($log->changes, JSON_UNESCAPED_SLASHES) }}</code>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
        @if ($logs->hasPages())
            <nav aria-label="Audit log pages" class="flex items-center justify-between gap-2 border-t border-border px-4 py-3 sm:px-6">
                @if ($logs->previousPageUrl())
                    <x-ui.button variant="outline" size="sm" icon="chevron-left" :href="$logs->previousPageUrl()" rel="prev">Newer</x-ui.button>
                @else
                    <x-ui.button variant="outline" size="sm" icon="chevron-left" disabled>Newer</x-ui.button>
                @endif
                @if ($logs->nextPageUrl())
                    <x-ui.button variant="outline" size="sm" icon-end="chevron-right" :href="$logs->nextPageUrl()" rel="next">Older</x-ui.button>
                @else
                    <x-ui.button variant="outline" size="sm" icon-end="chevron-right" disabled>Older</x-ui.button>
                @endif
            </nav>
        @endif
    </x-ui.card>
@endsection

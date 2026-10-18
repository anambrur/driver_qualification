@php
    // DriverController::show still names Font Awesome icons; map them to Lucide here.
    $icons = ['fa-sync-alt' => 'refresh-cw', 'fa-upload' => 'upload', 'fa-user-check' => 'user-round-check', 'fa-user-times' => 'user-round-x'];
    $tones = ['green' => 'success', 'red' => 'danger'];
@endphp
<x-ui.card title="Activity" description="Recent document and status changes.">
    @if ($activityLogs->isEmpty())
        <x-ui.empty-state icon="history" title="No activity yet" class="border-0 p-6!" />
    @else
        <ol class="relative ml-4 border-l border-border" role="list">
            @foreach ($activityLogs as $log)
                <li class="mb-6 ml-6 last:mb-0">
                    <span class="stat-icon absolute -left-4 flex size-8 items-center justify-center rounded-full ring-4 ring-card" data-tone="{{ $tones[$log['tone'] ?? ''] ?? 'brand' }}">
                        {{ svg('lucide-'.($icons[$log['icon']] ?? 'circle-dot'), 'size-4', ['aria-hidden' => 'true']) }}
                    </span>
                    <div class="flex flex-col gap-0.5 sm:flex-row sm:items-baseline sm:justify-between sm:gap-4">
                        <p class="text-sm font-medium">{{ $log['label'] }}</p>
                        <time datetime="{{ \Carbon\Carbon::parse($log['at'])->toIso8601String() }}" class="shrink-0 text-xs text-muted-foreground">
                            {{ \Carbon\Carbon::parse($log['at'])->format('M d, Y g:i A') }}
                        </time>
                    </div>
                    @if (! empty($log['detail']))
                        <p class="mt-1 text-sm text-muted-foreground">{{ $log['detail'] }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</x-ui.card>

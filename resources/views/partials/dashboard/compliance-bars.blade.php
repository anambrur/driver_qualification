{{-- Overdue / due-soon bars for a compliance card. Expects $stats, $total, $percent. --}}
@if ($total === 0)
    <div class="flex items-center gap-3 rounded-lg bg-success/10 p-4 text-sm">
        {{ svg('lucide-shield-check', 'size-5 shrink-0 text-success', ['aria-hidden' => 'true']) }}
        <span>All documents are current.</span>
    </div>
@else
    <dl class="space-y-4">
        @foreach (['overdue' => ['Expired', 'bg-destructive', 'text-destructive'], 'due_soon' => ['Due in 30 days', 'bg-warning', 'text-warning']] as $key => [$label, $bar, $text])
            <div>
                <div class="mb-1.5 flex items-baseline justify-between gap-2">
                    <dt class="text-sm text-muted-foreground">{{ $label }}</dt>
                    <dd class="text-lg font-semibold tabular-nums {{ $stats[$key] > 0 ? $text : '' }}">{{ $stats[$key] }}</dd>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-muted" role="presentation">
                    <div class="h-full rounded-full {{ $bar }}" style="width: {{ $percent($stats[$key], $total) }}%"></div>
                </div>
            </div>
        @endforeach
    </dl>
@endif

{{-- Checklist progress (req 5.3): answered ÷ items. Needs $answered and $total. --}}
@php($percent = $total === 0 ? 0 : intdiv($answered * 100, $total))
<div class="space-y-1.5" data-inspection-progress>
    <div class="flex items-center justify-between text-sm">
        <span class="text-muted-foreground"><span data-answered>{{ $answered }}</span> of {{ $total }} items answered</span>
        <span class="font-semibold tabular-nums"><span data-percent>{{ $percent }}</span>%</span>
    </div>
    <div class="h-2 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Checklist progress">
        <div class="h-full rounded-full bg-primary transition-[width]" style="width: {{ $percent }}%" data-bar></div>
    </div>
</div>

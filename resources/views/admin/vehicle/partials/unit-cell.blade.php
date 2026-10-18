{{-- DataTables cell (vehicles, trailers): plain partial, rendered per row. --}}
<div class="flex items-center gap-3">
    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">{{ svg('lucide-'.$icon, 'size-4', ['aria-hidden' => 'true']) }}</span>
    <div class="min-w-0">
        <p class="flex items-center gap-1.5 truncate font-medium text-foreground">
            {{ $unit }}<span class="font-normal text-muted-foreground">· {{ $title }}</span>
            @if ($notes)
                {{ svg('lucide-sticky-note', 'size-3.5 shrink-0 text-muted-foreground', ['aria-label' => 'Has notes', 'role' => 'img']) }}
            @endif
        </p>
        <p class="truncate font-mono text-xs text-muted-foreground">VIN {{ $vin }}</p>
    </div>
</div>

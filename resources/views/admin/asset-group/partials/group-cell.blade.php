{{-- DataTables cell: plain partial, rendered per row. --}}
<div class="flex items-center gap-3">
    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">{{ svg('lucide-users-round', 'size-4', ['aria-hidden' => 'true']) }}</span>
    <div class="min-w-0">
        <p class="truncate font-medium text-foreground">{{ $name }}</p>
        <p class="truncate text-xs text-muted-foreground">{{ $driver ? 'Driver: '.$driver : 'No driver' }}</p>
    </div>
</div>

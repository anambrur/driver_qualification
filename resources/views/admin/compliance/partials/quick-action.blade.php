{{-- One link in a compliance dashboard's Quick Actions card: $href, $icon, $tone, $label, $hint. --}}
<a href="{{ $href }}" class="flex items-center gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/50">
    <span class="stat-icon flex size-8 shrink-0 items-center justify-center rounded-lg" data-tone="{{ $tone }}">
        {{ svg('lucide-'.$icon, 'size-4', ['aria-hidden' => 'true']) }}
    </span>
    <span class="min-w-0 flex-1">
        <span class="block text-sm font-medium text-foreground">{{ $label }}</span>
        <span class="block text-xs text-muted-foreground">{{ $hint }}</span>
    </span>
    {{ svg('lucide-chevron-right', 'size-4 shrink-0 text-muted-foreground', ['aria-hidden' => 'true']) }}
</a>

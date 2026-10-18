{{-- DataTables cell (platform lists): a title, linked when $href is set, and a muted second line. --}}
<div class="min-w-0">
    @if (filled($href ?? null))
        <a href="{{ $href }}" class="font-medium text-foreground underline-offset-2 hover:underline">{{ $title }}</a>
    @else
        <p class="font-medium text-foreground">{{ filled($title) ? $title : '—' }}</p>
    @endif
    @if (filled($sub ?? null))
        <p class="truncate text-xs text-muted-foreground">{{ $sub }}</p>
    @endif
</div>

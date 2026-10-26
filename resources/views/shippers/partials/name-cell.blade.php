{{-- Plain partial (DataTables row): name and street line --}}
<div class="min-w-0">
    @if ($href)
        <a href="{{ $href }}" class="font-medium text-foreground hover:underline">{{ $shipper->name }}</a>
    @else
        <span class="font-medium text-foreground">{{ $shipper->name }}</span>
    @endif
    <p class="truncate text-xs text-muted-foreground">{{ $shipper->address_line1 }}</p>
</div>

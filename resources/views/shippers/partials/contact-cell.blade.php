{{-- Plain partial (DataTables row) --}}
@if ($shipper->contact_name || $shipper->phone || $shipper->email)
    <div class="min-w-0 text-sm">
        @if ($shipper->contact_name)
            <p class="text-foreground">{{ $shipper->contact_name }}</p>
        @endif
        <p class="truncate text-xs text-muted-foreground">{{ collect([$shipper->phone, $shipper->email])->filter()->join(' · ') }}</p>
    </div>
@else
    <span class="text-muted-foreground">—</span>
@endif

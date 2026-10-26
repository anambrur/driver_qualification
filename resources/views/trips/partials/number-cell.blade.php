{{-- Plain partial (DataTables row): the number links to the trip --}}
<div class="min-w-0">
    <a href="{{ route('trips.show', $trip) }}" class="font-medium text-foreground hover:underline">{{ $trip->label() }}</a>
    <p class="truncate text-xs text-muted-foreground">{{ $trip->isNumberLocked() ? ($trip->product ?: 'No product yet') : 'Number not confirmed' }}</p>
</div>

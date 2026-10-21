{{-- DataTables cell (vehicles, trailers): the licence plate and its state, or a dash. --}}
@if (filled($number))
    <span class="font-mono text-sm tracking-wide text-foreground">{{ $number }}</span>@if (filled($state))<span class="text-muted-foreground"> · {{ $state }}</span>@endif
@else
    <span class="text-muted-foreground">—</span>
@endif

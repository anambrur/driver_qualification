{{-- DataTables cell: service date and up to two categories. --}}
<p class="text-sm font-medium text-foreground">{{ $date }}</p>
@if ($categories->isNotEmpty())
    <div class="mt-1 flex flex-wrap gap-1">
        @foreach ($categories->take(2) as $category)
            <span class="badge" data-variant="secondary">{{ $category }}</span>
        @endforeach
        @if ($categories->count() > 2)
            <span class="badge" data-variant="outline">+{{ $categories->count() - 2 }}</span>
        @endif
    </div>
@endif

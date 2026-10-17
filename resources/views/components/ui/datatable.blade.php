{{--
    Server-side DataTable shell. Columns are <th> in the default slot; JS setup stays on the page:
        window.dqTable('#drivers', { ajax: '...', columns: [...] })
    Slots: toolbar (filters), empty (no records yet), zero (no search matches).
--}}
@props(['id', 'title' => null, 'description' => null])
@once
    @push('datatable')
        @vite('resources/js/datatable.js')
    @endpush
@endonce
<section id="{{ $id }}" {{ $attributes->class('card gap-0! py-0!') }}>
    @if ($title || isset($toolbar))
        <header class="flex! flex-col gap-3 border-b border-border px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            @if ($title)
                <div class="min-w-0">
                    <h2 class="text-base font-medium">{{ $title }}</h2>
                    @if ($description)
                        <p class="text-sm text-muted-foreground">{{ $description }}</p>
                    @endif
                </div>
            @endif
            {{ $toolbar ?? '' }}
        </header>
    @endif
    <table class="table table-stack w-full" aria-label="{{ $title ?? 'Records' }}">
        <thead>
            <tr>{{ $slot }}</tr>
        </thead>
        <tbody></tbody>
    </table>
    <template data-dt-empty>
        @isset($empty)
            {{ $empty }}
        @else
            <x-ui.empty-state title="No records yet" class="border-0" />
        @endisset
    </template>
    <template data-dt-zero>
        @isset($zero)
            {{ $zero }}
        @else
            <x-ui.empty-state icon="search-x" title="No matches" description="Try a different search or clear the filters." class="border-0" />
        @endisset
    </template>
</section>

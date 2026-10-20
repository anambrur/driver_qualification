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
    {{-- One compact bar: title on the left; search, page filters and page size on the right (moved in by dqTable). --}}
    <header class="flex! flex-col gap-3 border-b border-border px-4 py-3 sm:px-5 lg:flex-row lg:items-center lg:justify-between">
        @if ($title)
            <div class="min-w-0">
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                @if ($description)
                    <p class="text-sm text-muted-foreground">{{ $description }}</p>
                @endif
            </div>
        @endif
        <div class="flex flex-wrap items-center gap-2 lg:ml-auto lg:justify-end">
            <div data-dt-slot="search" class="min-w-0 flex-1 sm:flex-none"></div>
            {{ $toolbar ?? '' }}
            <div data-dt-slot="length"></div>
        </div>
    </header>
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

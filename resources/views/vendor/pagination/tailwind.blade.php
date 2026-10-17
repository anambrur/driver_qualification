@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-col items-center justify-between gap-3 sm:flex-row">
        <p class="text-sm text-muted-foreground">
            @if ($paginator->firstItem())
                <span class="font-medium text-foreground">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-foreground">{{ $paginator->lastItem() }}</span>
                {{ __('of') }}
                <span class="font-medium text-foreground">{{ $paginator->total() }}</span>
            @else
                {{ $paginator->count() }} {{ __('results') }}
            @endif
        </p>

        <ul class="flex flex-wrap items-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="btn opacity-50" data-variant="ghost" data-size="icon" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">{{ svg('lucide-chevron-left', '', ['aria-hidden' => 'true']) }}</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn" data-variant="ghost" data-size="icon" aria-label="{{ __('pagination.previous') }}">{{ svg('lucide-chevron-left', '', ['aria-hidden' => 'true']) }}</a>
                @endif
            </li>

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="max-sm:hidden"><span class="px-2 text-muted-foreground" aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li @class(['max-sm:hidden' => $page != $paginator->currentPage()])>
                            @if ($page == $paginator->currentPage())
                                <span class="btn" data-variant="outline" data-size="icon" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="btn" data-variant="ghost" data-size="icon" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn" data-variant="ghost" data-size="icon" aria-label="{{ __('pagination.next') }}">{{ svg('lucide-chevron-right', '', ['aria-hidden' => 'true']) }}</a>
                @else
                    <span class="btn opacity-50" data-variant="ghost" data-size="icon" aria-disabled="true" aria-label="{{ __('pagination.next') }}">{{ svg('lucide-chevron-right', '', ['aria-hidden' => 'true']) }}</span>
                @endif
            </li>
        </ul>
    </nav>
@endif

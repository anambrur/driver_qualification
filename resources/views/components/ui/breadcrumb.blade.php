@props(['items' => []]) {{-- [['label' => 'Drivers', 'href' => route(...)], ['label' => 'Edit']] --}}
<nav aria-label="Breadcrumb" {{ $attributes->class('breadcrumb') }}>
    <ol class="flex flex-wrap items-center break-words">
        @foreach ($items as $item)
            <li class="inline-flex items-center">
                @if (! $loop->last && ! empty($item['href']))
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @else
                    <span aria-current="{{ $loop->last ? 'page' : 'false' }}">{{ $item['label'] }}</span>
                @endif
            </li>
            @unless ($loop->last)
                <li aria-hidden="true" class="inline-flex items-center">{{ svg('lucide-chevron-right') }}</li>
            @endunless
        @endforeach
    </ol>
</nav>

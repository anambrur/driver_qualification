@props(['title' => null, 'description' => null, 'size' => null, 'flush' => false])
<section {{ $attributes->class('card')->merge(['data-size' => $size]) }}>
    @if ($title || isset($action))
        <header>
            @if ($title)
                <h2>{{ $title }}</h2>
            @endif
            @if ($description)
                <p>{{ $description }}</p>
            @endif
            @isset($action)
                <div {{ $action->attributes->class('card-action flex items-center gap-2') }}>{{ $action }}</div>
            @endisset
        </header>
    @endif
    <div @class(['min-w-0', 'px-6 in-data-[size=sm]:px-4' => ! $flush])>
        {{ $slot }}
    </div>
    @isset($footer)
        <footer {{ $footer->attributes->class('gap-2 px-6 in-data-[size=sm]:px-4') }}>{{ $footer }}</footer>
    @endisset
</section>

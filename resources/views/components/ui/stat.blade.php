@props(['label', 'value', 'icon' => null, 'hint' => null, 'tone' => 'brand', 'href' => null])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['card flex-row! items-start gap-4 px-5 py-5', 'transition hover:ring-foreground/20 focus-visible:ring-ring' => $href]) }}>
    @if ($icon)
        <span class="stat-icon flex size-10 shrink-0 items-center justify-center rounded-lg" data-tone="{{ $tone }}">
            {{ svg('lucide-'.$icon, 'size-5', ['aria-hidden' => 'true']) }}
        </span>
    @endif
    <div class="min-w-0">
        <p class="text-sm text-muted-foreground">{{ $label }}</p>
        <p class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-foreground">{{ $value }}</p>
        @if ($hint)
            <p class="mt-1 text-xs text-muted-foreground">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>

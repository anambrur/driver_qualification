@props([
    'variant' => null, // primary | secondary | outline | ghost | destructive | link
    'size' => null, // xs | sm | lg | icon | icon-sm | icon-lg
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'iconEnd' => null,
    'loading' => false,
])
@php($tag = $href ? 'a' : 'button')
<{{ $tag }}
    @if ($href) href="{{ $href }}" @else type="{{ $type }}" @endif
    {{ $attributes->class('btn')->merge(array_filter(['data-variant' => $variant, 'data-size' => $size])) }}
    @if ($loading) aria-busy="true" @if (! $href) disabled @endif @endif>
    @if ($loading)
        {{ svg('lucide-loader-circle', 'animate-spin', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }}
    @elseif ($icon)
        {{ svg('lucide-'.$icon, '', ['data-icon' => 'inline-start', 'aria-hidden' => 'true']) }}
    @endif
    {{ $slot }}
    @if ($iconEnd)
        {{ svg('lucide-'.$iconEnd, '', ['data-icon' => 'inline-end', 'aria-hidden' => 'true']) }}
    @endif
</{{ $tag }}>

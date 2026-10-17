@props([
    'tone' => null, // neutral | brand | success | warning | danger | info
    'variant' => null, // Basecoat variants: primary | secondary | outline | destructive
    'dot' => false,
])
<span {{ $attributes->class('badge')->merge(array_filter(['data-tone' => $tone, 'data-variant' => $variant])) }}>
    @if ($dot)
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>

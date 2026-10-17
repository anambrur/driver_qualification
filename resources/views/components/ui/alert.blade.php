@props([
    'tone' => null, // success | warning | danger | info (default: neutral)
    'title' => null,
    'icon' => null,
    'dismissible' => false,
])
@php
    $icon ??= match ($tone) {
        'success' => 'circle-check',
        'warning' => 'triangle-alert',
        'danger' => 'circle-alert',
        default => 'info',
    };
@endphp
<div role="{{ in_array($tone, ['danger', 'warning'], true) ? 'alert' : 'status' }}"
    @if ($dismissible) x-data="{ open: true }" x-show="open" @endif
    {{ $attributes->class(['alert relative', 'pr-10' => $dismissible])->merge(array_filter(['data-tone' => $tone, 'data-variant' => $tone === 'danger' ? 'destructive' : null])) }}>
    {{ svg('lucide-'.$icon, '', ['aria-hidden' => 'true']) }}
    @if ($title)
        <h2>{{ $title }}</h2>
    @endif
    @if (trim($slot) !== '')
        <section>{{ $slot }}</section>
    @endif
    @if ($dismissible)
        <button type="button" class="btn absolute right-2 top-2" data-variant="ghost" data-size="icon-xs" x-on:click="open = false" aria-label="Dismiss">
            {{ svg('lucide-x', '', ['aria-hidden' => 'true']) }}
        </button>
    @endif
</div>

{{-- One label/value pair inside a <dl>. Empty values show an em dash. --}}
@props(['label', 'value' => null, 'mono' => false])
<div {{ $attributes->class('min-w-0') }}>
    <dt class="text-xs text-muted-foreground">{{ $label }}</dt>
    <dd @class(['mt-1 text-sm font-medium text-foreground break-words', 'font-mono tracking-wide' => $mono])>
        @if (trim($slot) !== '')
            {{ $slot }}
        @else
            {{ filled($value) ? $value : '—' }}
        @endif
    </dd>
</div>

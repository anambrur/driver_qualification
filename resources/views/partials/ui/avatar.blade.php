{{-- Plain partial (not a component) so DataTables can render it per row cheaply. --}}
@php
    $initials = collect(preg_split('/\s+/', trim((string) ($name ?? ''))) ?: [])
        ->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: '?';
@endphp
<span class="avatar shrink-0 {{ $class ?? '' }}" @if (! empty($size)) data-size="{{ $size }}" @endif>
    @if (! empty($src))
        <img src="{{ $src }}" alt="{{ $name }}" loading="lazy" decoding="async" width="40" height="40" class="size-full object-cover">
    @else
        <span class="flex size-full items-center justify-center text-xs font-medium" aria-hidden="true">{{ $initials }}</span>
        <span class="sr-only">{{ $name }}</span>
    @endif
</span>

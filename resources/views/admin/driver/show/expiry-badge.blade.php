@php
    $expires = $date ? \Carbon\Carbon::parse($date) : null;
    [$tone, $label] = match (true) {
        $expires === null => ['neutral', 'No expiry'],
        $expires->isPast() => ['danger', 'Expired'],
        $expires->lte(now()->addDays(30)) => ['warning', 'Expires soon'],
        default => ['success', 'Valid'],
    };
@endphp
<x-ui.badge :tone="$tone" dot>{{ $label }}</x-ui.badge>

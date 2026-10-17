{{-- Breeze compatibility: prefer <x-ui.field name="..."> in new code. --}}
@props(['messages'])

@if ($messages)
    <ul role="alert" {{ $attributes->class('space-y-1 text-sm text-destructive') }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif

{{-- Breeze compatibility: prefer <x-ui.field label="..."> in new code. --}}
@props(['value'])

<label {{ $attributes->class('label mb-2') }}>
    {{ $value ?? $slot }}
</label>

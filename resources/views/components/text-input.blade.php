{{-- Breeze compatibility: prefer <x-ui.input> in new code. --}}
@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['type' => 'text'])->class('input w-full') }}>

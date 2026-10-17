{{-- Breeze compatibility: prefer <x-ui.button variant="outline"> in new code. --}}
<button {{ $attributes->merge(['type' => 'button', 'data-variant' => 'outline'])->class('btn') }}>
    {{ $slot }}
</button>

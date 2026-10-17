{{-- Breeze compatibility: prefer <x-ui.button> in new code. --}}
<button {{ $attributes->merge(['type' => 'submit'])->class('btn') }}>
    {{ $slot }}
</button>

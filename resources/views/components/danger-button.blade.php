{{-- Breeze compatibility: prefer <x-ui.button variant="destructive"> in new code. --}}
<button {{ $attributes->merge(['type' => 'submit'])->class('btn bg-destructive! text-white! hover:bg-destructive/90!') }}>
    {{ $slot }}
</button>

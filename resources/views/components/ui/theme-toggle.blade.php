{{-- Light / dark / system. State lives in Alpine.store('theme') (resources/js/theme.js). --}}
<x-ui.dropdown-menu {{ $attributes }}>
    <x-slot:trigger class="btn" data-variant="ghost" data-size="icon" aria-label="Change colour theme">
        {{ svg('lucide-sun', 'dark:hidden', ['aria-hidden' => 'true']) }}
        {{ svg('lucide-moon', 'hidden dark:block', ['aria-hidden' => 'true']) }}
    </x-slot:trigger>
    <div role="heading">Theme</div>
    @foreach (['light' => ['Light', 'sun'], 'dark' => ['Dark', 'moon'], 'system' => ['System', 'monitor']] as $mode => [$label, $icon])
        <div role="menuitemradio" x-data :aria-checked="$store.theme.mode === '{{ $mode }}'" x-on:click="$store.theme.set('{{ $mode }}')">
            {{ svg('lucide-'.$icon, '', ['aria-hidden' => 'true']) }}
            {{ $label }}
            <span class="ml-auto" x-show="$store.theme.mode === '{{ $mode }}'" x-cloak>{{ svg('lucide-check', 'size-4', ['aria-hidden' => 'true']) }}</span>
        </div>
    @endforeach
</x-ui.dropdown-menu>

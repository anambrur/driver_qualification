{{--
    <x-ui.tabs id="settings" :tabs="['general' => 'General', 'billing' => 'Billing']">
        <x-ui.tab-panel tabs="settings" name="general" selected>...</x-ui.tab-panel>
        <x-ui.tab-panel tabs="settings" name="billing">...</x-ui.tab-panel>
    </x-ui.tabs>
--}}
@props(['id', 'tabs' => [], 'selected' => null])
@php($selected ??= array_key_first($tabs))
<div id="{{ $id }}" {{ $attributes->class('tabs w-full') }}>
    <nav role="tablist" aria-orientation="horizontal" class="max-w-full overflow-x-auto no-scrollbar">
        @foreach ($tabs as $name => $label)
            <button type="button" role="tab" id="{{ $id }}-tab-{{ $name }}" aria-controls="{{ $id }}-panel-{{ $name }}"
                aria-selected="{{ $name === $selected ? 'true' : 'false' }}" tabindex="{{ $name === $selected ? '0' : '-1' }}">{{ $label }}</button>
        @endforeach
    </nav>
    {{ $slot }}
</div>

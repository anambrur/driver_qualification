{{--
    <x-ui.dropdown-menu>
        <x-slot:trigger class="btn" data-variant="outline">Options</x-slot:trigger>
        <a role="menuitem" href="...">Edit</a>
        <hr role="separator">
        <button role="menuitem" type="button">Delete</button>
    </x-ui.dropdown-menu>
--}}
@props(['id' => null, 'align' => 'end', 'side' => 'bottom'])
@php($id ??= 'menu-'.\Illuminate\Support\Str::random(8))
<div id="{{ $id }}" {{ $attributes->class('dropdown-menu') }}>
    <button type="button" id="{{ $id }}-trigger" aria-haspopup="menu" aria-controls="{{ $id }}-menu" aria-expanded="false" {{ $trigger->attributes }}>
        {{ $trigger }}
    </button>
    <div id="{{ $id }}-popover" data-popover aria-hidden="true" data-align="{{ $align }}" data-side="{{ $side }}" class="min-w-56">
        <div role="menu" id="{{ $id }}-menu" aria-labelledby="{{ $id }}-trigger">
            {{ $slot }}
        </div>
    </div>
</div>

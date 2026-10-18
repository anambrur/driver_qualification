{{-- Password input with a show/hide toggle. Never repopulated from old(). --}}
@props(['name' => 'password'])
<div class="relative" x-data="{ show: false }">
    <x-ui.input type="password" :name="$name" x-bind:type="show ? 'text' : 'password'" :attributes="$attributes->class('pr-10')" />
    <button type="button" class="btn absolute right-1 top-1/2 -translate-y-1/2" data-variant="ghost" data-size="icon-sm"
        x-on:click="show = !show" x-bind:aria-pressed="show" aria-label="Show password">
        {{ svg('lucide-eye', '', ['aria-hidden' => 'true', 'x-show' => '!show']) }}
        {{ svg('lucide-eye-off', '', ['aria-hidden' => 'true', 'x-show' => 'show', 'x-cloak' => '']) }}
    </button>
</div>

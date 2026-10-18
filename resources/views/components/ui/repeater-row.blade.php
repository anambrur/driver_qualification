{{-- One row of a [data-repeater] list (see resources/js/driver-form.js). --}}
@props(['title', 'number', 'removable' => true])
<div data-repeater-row {{ $attributes->class('grid gap-4 rounded-lg border border-border p-4') }}>
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-medium">{{ $title }} <span data-row-number>{{ $number }}</span></h3>
        @if ($removable)
            <x-ui.button variant="ghost" size="sm" icon="trash-2" data-repeater-remove class="text-destructive">Remove</x-ui.button>
        @endif
    </div>
    {{ $slot }}
</div>

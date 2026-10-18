{{-- Back link + "save" submit for a step form (the controllers read action=save). --}}
@props(['back', 'backLabel', 'submit'])
<div {{ $attributes->class('flex flex-col-reverse gap-3 border-t pt-6 sm:flex-row sm:items-center sm:justify-between') }}>
    <x-ui.button :href="$back" variant="outline" icon="arrow-left">{{ $backLabel }}</x-ui.button>
    <x-ui.button type="submit" name="action" value="save" variant="primary" icon="save">{{ $submit }}</x-ui.button>
</div>

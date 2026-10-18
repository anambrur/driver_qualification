{{-- Back link + "save" submit for a step form (the controllers read action=save). The admin wizard adds `skip`: a link with `skipHref`, else an action=skip submit. --}}
@props(['back', 'backLabel', 'submit', 'skip' => null, 'skipHref' => null])
<div {{ $attributes->class('flex flex-col-reverse gap-3 border-t pt-6 sm:flex-row sm:items-center sm:justify-between') }}>
    <x-ui.button :href="$back" variant="outline" icon="arrow-left">{{ $backLabel }}</x-ui.button>
    @if ($skip)
    <div class="flex flex-col gap-3 sm:flex-row">
        <x-ui.button type="submit" name="action" value="save" variant="primary" icon="save">{{ $submit }}</x-ui.button>
        @if ($skipHref)
            <x-ui.button :href="$skipHref" variant="outline">{{ $skip }}</x-ui.button>
        @else
            <x-ui.button type="submit" name="action" value="skip" variant="outline">{{ $skip }}</x-ui.button>
        @endif
    </div>
    @else
    <x-ui.button type="submit" name="action" value="save" variant="primary" icon="save">{{ $submit }}</x-ui.button>
    @endif
</div>

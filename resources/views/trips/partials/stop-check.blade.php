{{--
    A checkbox of the stops form, the x-ui.checkbox markup without its old-input lookup: the trip page
    has three forms, and a failed Details save must not untick these. Needs $name, $label, $checked.
--}}
@php($id = \App\Support\Ui\Field::id($name))
<label for="{{ $id }}" class="label inline-flex items-start gap-3 font-normal">
    <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="1" @checked($checked) class="input mt-0.5" @if ($toggle ?? false) data-pickup-toggle @endif>
    <span class="grid gap-1 leading-snug">
        <span class="font-medium text-foreground">{{ $label }}</span>
        @if ($description)
            <span class="text-sm text-muted-foreground">{{ $description }}</span>
        @endif
    </span>
</label>

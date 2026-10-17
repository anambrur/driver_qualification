@props(['name' => null, 'label' => null, 'value' => '1', 'checked' => false, 'switch' => false, 'description' => null])
@php
    $id = $attributes->get('id') ?? \App\Support\Ui\Field::id($name);
    $errorKey = \App\Support\Ui\Field::errorKey($name);
    $isChecked = $errorKey && old() ? (string) old($errorKey) === (string) $value : (bool) $checked;
@endphp
<label @if ($id) for="{{ $id }}" @endif class="label inline-flex items-start gap-3 font-normal">
    <input type="checkbox" @if ($name) name="{{ $name }}" @endif @if ($id) id="{{ $id }}" @endif value="{{ $value }}"
        @if ($switch) role="switch" @endif @checked($isChecked)
        {{ $attributes->except('id')->class('input mt-0.5') }}>
    @if ($label || $description)
        <span class="grid gap-1 leading-snug">
            @if ($label)
                <span class="font-medium text-foreground">{{ $label }}</span>
            @endif
            @if ($description)
                <span class="text-sm text-muted-foreground">{{ $description }}</span>
            @endif
        </span>
    @endif
</label>

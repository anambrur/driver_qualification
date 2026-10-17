@props(['name' => null, 'options' => null, 'value' => null, 'placeholder' => null])
@php
    $id = $attributes->get('id') ?? \App\Support\Ui\Field::id($name);
    $errorKey = \App\Support\Ui\Field::errorKey($name);
    $invalid = $errorKey && $errors->has($errorKey);
    $selected = (string) ($errorKey ? old($errorKey, $value) : $value);
@endphp
<select @if ($name) name="{{ $name }}" @endif @if ($id) id="{{ $id }}" @endif
    {{ $attributes->except('id')->class('select w-full')->merge(array_filter([
        'aria-invalid' => $invalid ? 'true' : null,
        'aria-describedby' => $invalid ? $id.'-error' : null,
    ])) }}>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    @if ($options !== null)
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    @endif
    {{ $slot }}
</select>

@props(['name' => null, 'value' => null])
@php
    $id = $attributes->get('id') ?? \App\Support\Ui\Field::id($name);
    $errorKey = \App\Support\Ui\Field::errorKey($name);
    $invalid = $errorKey && $errors->has($errorKey);
@endphp
<textarea @if ($name) name="{{ $name }}" @endif @if ($id) id="{{ $id }}" @endif
    {{ $attributes->except('id')->class('textarea')->merge(array_filter([
        'aria-invalid' => $invalid ? 'true' : null,
        'aria-describedby' => $invalid ? $id.'-error' : null,
    ])) }}>{{ $errorKey ? old($errorKey, $value ?? (string) $slot) : ($value ?? $slot) }}</textarea>

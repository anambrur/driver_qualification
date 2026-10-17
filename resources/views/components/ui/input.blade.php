@props(['name' => null, 'type' => 'text', 'value' => null])
@php
    $id = $attributes->get('id') ?? \App\Support\Ui\Field::id($name);
    $errorKey = \App\Support\Ui\Field::errorKey($name);
    $invalid = $errorKey && $errors->has($errorKey);
    $keepsValue = ! in_array($type, ['password', 'file'], true);
@endphp
<input type="{{ $type }}"
    @if ($name) name="{{ $name }}" @endif
    @if ($id) id="{{ $id }}" @endif
    @if ($keepsValue && ($errorKey || $value !== null)) value="{{ $errorKey ? old($errorKey, $value) : $value }}" @endif
    {{ $attributes->except('id')->class('input w-full')->merge(array_filter([
        'aria-invalid' => $invalid ? 'true' : null,
        'aria-describedby' => $invalid ? $id.'-error' : null,
    ])) }}>

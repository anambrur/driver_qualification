@props(['label' => null, 'name' => null, 'for' => null, 'hint' => null, 'required' => false, 'orientation' => null])
@php
    $for ??= \App\Support\Ui\Field::id($name);
    $errorKey = \App\Support\Ui\Field::errorKey($name);
    $error = $errorKey ? $errors->first($errorKey) : null;
@endphp
<div {{ $attributes->class('field gap-2')->merge(array_filter(['data-invalid' => $error ? 'true' : null, 'data-orientation' => $orientation])) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="label">
            {{ $label }}
            @if ($required)
                <span class="text-destructive" aria-hidden="true">*</span>
            @endif
        </label>
    @endif
    {{ $slot }}
    @if ($hint)
        <p @if ($for) id="{{ $for }}-hint" @endif class="text-sm text-muted-foreground">{{ $hint }}</p>
    @endif
    @if ($error)
        <p role="alert" @if ($for) id="{{ $for }}-error" @endif class="text-sm text-destructive">{{ $error }}</p>
    @endif
</div>

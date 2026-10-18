@props(['name', 'legend' => null, 'options' => ['yes' => 'Yes', 'no' => 'No'], 'value' => null, 'required' => false, 'hint' => null])
@php
    $baseId = \App\Support\Ui\Field::id($name);
    $errorKey = \App\Support\Ui\Field::errorKey($name);
    $error = $errors->first($errorKey);
    $selected = (string) old($errorKey, $value);
@endphp
<fieldset id="{{ $baseId }}" {{ $attributes->class('grid gap-2')->merge(array_filter([
    'data-invalid' => $error ? 'true' : null,
    'aria-describedby' => $error ? $baseId.'-error' : ($hint ? $baseId.'-hint' : null),
])) }}>
    @if ($legend)
        <legend class="label mb-2 leading-snug">
            {{ $legend }}
            @if ($required)
                <span class="text-destructive" aria-hidden="true">*</span>
            @endif
        </legend>
    @endif
    <div class="flex flex-wrap gap-x-6 gap-y-2">
        @foreach ($options as $optionValue => $optionLabel)
            <label for="{{ $baseId }}-{{ $optionValue }}" class="label inline-flex min-h-6 items-center gap-2 font-normal">
                <input type="radio" name="{{ $name }}" id="{{ $baseId }}-{{ $optionValue }}" value="{{ $optionValue }}" class="input"
                    @checked($selected === (string) $optionValue) @if ($required && $loop->first) required @endif
                    @if ($error) aria-invalid="true" @endif>
                {{ $optionLabel }}
            </label>
        @endforeach
    </div>
    @if ($hint)
        <p id="{{ $baseId }}-hint" class="text-sm text-muted-foreground">{{ $hint }}</p>
    @endif
    @if ($error)
        <p role="alert" id="{{ $baseId }}-error" class="text-sm text-destructive">{{ $error }}</p>
    @endif
</fieldset>

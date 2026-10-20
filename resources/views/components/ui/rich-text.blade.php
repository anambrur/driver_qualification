{{-- A Trix editor posting HTML as $name. Pages using it push the editor bundle (resources/js/editor.js). --}}
@props(['name', 'value' => null])
@php
    $id = \App\Support\Ui\Field::id($name);
    $errorKey = \App\Support\Ui\Field::errorKey($name);
@endphp
@once
    @push('styles')
        @vite('resources/js/editor.js')
    @endpush
@endonce
<input type="hidden" id="{{ $id }}-input" name="{{ $name }}" value="{{ old($errorKey, $value) }}">
<trix-editor id="{{ $id }}" input="{{ $id }}-input" {{ $attributes->class(['rich-text prose max-w-none dark:prose-invert', 'border-destructive' => $errors->has($errorKey)]) }}></trix-editor>

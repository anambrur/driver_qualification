{{--
    File dropzone for the application steps. The step's script reads these ids:
    {name}, {name}_preview, {name}_preview_img, {name}_pdf_preview, {name}_pdf_preview_content, removePreview('{name}').
--}}
@props([
    'name',
    'label',
    'button',
    'accept' => 'image/*',
    'types' => 'PNG, JPG, JPEG up to 5MB',
    'required' => false,
    'current' => null, // URL of the stored file
    'currentImage' => true,
    'currentLabel' => null,
    'removeLabel' => 'Remove New File',
    'pdf' => false,
])
<div {{ $attributes->class('grid content-start gap-3') }}>
    <p class="label">
        {{ $label }}
        @if ($required)
            <span class="text-destructive" aria-hidden="true">*</span>
        @endif
    </p>

    @if ($current)
        <div class="grid gap-2">
            <p class="text-sm text-muted-foreground">Current {{ $currentLabel ?? $label }}:</p>
            @if ($currentImage)
                <a href="{{ $current }}" target="_blank" rel="noopener" class="group relative block overflow-hidden rounded-lg border bg-muted/40">
                    <img src="{{ $current }}" alt="{{ $currentLabel ?? $label }}" class="h-48 w-full object-contain">
                    <span class="absolute inset-0 flex items-center justify-center bg-black/50 text-sm font-medium text-white opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">View Full Size</span>
                </a>
            @else
                <div class="flex items-center gap-3 rounded-lg border bg-muted/40 p-4">
                    <x-ui.icon name="file-text" class="size-10 shrink-0 text-destructive" />
                    <div class="grid gap-0.5 text-left">
                        <p class="text-sm font-medium">{{ $currentLabel ?? $label }}</p>
                        <p class="text-xs text-muted-foreground">PDF Document</p>
                        <a href="{{ $current }}" target="_blank" rel="noopener" class="text-sm font-medium text-primary underline-offset-4 hover:underline">View Document</a>
                    </div>
                </div>
            @endif
            <p class="text-xs text-muted-foreground">Upload a new {{ $pdf ? 'file' : 'image' }} to replace this one.</p>
        </div>
    @endif

    <div @class([
        'rounded-lg border-2 border-dashed p-6 text-center transition-colors has-[input:focus-visible]:border-ring',
        'border-destructive' => $errors->has($name),
    ])>
        <x-ui.icon name="image-plus" class="mx-auto size-10 text-muted-foreground" />
        <label for="{{ $name }}" class="mt-2 inline-block cursor-pointer text-sm font-medium text-primary underline-offset-4 hover:underline">
            <span>{{ $button }}</span>
            <input id="{{ $name }}" name="{{ $name }}" type="file" class="sr-only" accept="{{ $accept }}" @if ($required) required @endif
                @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
        </label>
        <p class="mt-1 text-xs text-muted-foreground">{{ $types }}</p>

        <div id="{{ $name }}_preview" class="mt-4 hidden">
            <img id="{{ $name }}_preview_img" alt="" class="mx-auto h-32 rounded-lg border object-contain">
            <button type="button" onclick="removePreview('{{ $name }}')" class="mt-2 text-sm font-medium text-destructive hover:underline">{{ $removeLabel }}</button>
        </div>
        @if ($pdf)
            <div id="{{ $name }}_pdf_preview" class="mt-4 hidden">
                <div id="{{ $name }}_pdf_preview_content" class="text-center"></div>
                <button type="button" onclick="removePreview('{{ $name }}')" class="mt-2 text-sm font-medium text-destructive hover:underline">{{ $removeLabel }}</button>
            </div>
        @endif
    </div>

    @error($name)
        <p id="{{ $name }}-error" role="alert" class="text-sm text-destructive">{{ $message }}</p>
    @enderror
</div>

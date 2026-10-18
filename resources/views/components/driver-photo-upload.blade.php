@php
    $existingPhotoUrl = $existingPhotoUrl ?? null;
    $photoValue = old('photo');
@endphp

<div>
    <label for="photo" class="label mb-2">
        Upload Driver Photo
    </label>

    @if ($existingPhotoUrl)
        <div class="mb-3" id="photo_existing">
            <p class="mb-2 text-xs text-muted-foreground">Current photo</p>
            <img src="{{ $existingPhotoUrl }}" alt="Driver photo"
                class="h-32 w-32 rounded-lg object-cover border">
        </div>
    @endif

    <div class="flex items-center justify-center w-full">
        <label for="photo"
            class="flex h-32 w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed bg-muted/40 hover:bg-muted">
            <div class="flex flex-col items-center justify-center pt-5 pb-6" id="photo_dropzone_label">
                {{ svg('lucide-cloud-upload', 'mb-3 size-7 text-muted-foreground', ['aria-hidden' => 'true']) }}
                <p class="mb-2 text-sm text-muted-foreground">
                    <span class="font-semibold">Click to upload</span> or drag and drop
                </p>
                <p class="text-xs text-muted-foreground">PNG, JPG, GIF, SVG (MAX. 2MB)</p>
            </div>
            <input id="photo" name="photo" type="file" class="input w-full hidden" accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp" />
        </label>
    </div>

    <div id="photo_preview" class="hidden mt-4 text-center">
        <img id="photo_preview_img"
            class="mx-auto h-32 w-32 object-cover rounded-lg border"
            alt="Photo preview">
        <p id="photo_filename" class="mt-2 text-xs text-muted-foreground"></p>
        <button type="button" id="photo_remove_btn"
            class="mt-2 text-sm text-destructive">
            Remove
        </button>
    </div>

    @error('photo')
        <p class="mt-1 text-sm text-destructive">{{ $message }}</p>
    @enderror
</div>

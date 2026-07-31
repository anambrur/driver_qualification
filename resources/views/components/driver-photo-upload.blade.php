@php
    $existingPhoto = $existingPhoto ?? null;
    $photoValue = old('photo');
@endphp

<div>
    <label for="photo" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
        Upload Driver Photo
    </label>

    @if ($existingPhoto)
        <div class="mb-3" id="photo_existing">
            <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">Current photo</p>
            <img src="{{ asset('storage/' . $existingPhoto) }}" alt="Driver photo"
                class="h-32 w-32 rounded-lg object-cover border border-gray-200 dark:border-gray-700">
        </div>
    @endif

    <div class="flex items-center justify-center w-full">
        <label for="photo"
            class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:hover:border-gray-500 dark:hover:bg-gray-600">
            <div class="flex flex-col items-center justify-center pt-5 pb-6" id="photo_dropzone_label">
                <i class="fas fa-cloud-upload-alt mb-3 text-2xl text-gray-500 dark:text-gray-400"></i>
                <p class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-semibold">Click to upload</span> or drag and drop
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">PNG, JPG, GIF, SVG (MAX. 2MB)</p>
            </div>
            <input id="photo" name="photo" type="file" class="hidden" accept="image/jpeg,image/png,image/gif,image/svg+xml,image/webp" />
        </label>
    </div>

    <div id="photo_preview" class="hidden mt-4 text-center">
        <img id="photo_preview_img"
            class="mx-auto h-32 w-32 object-cover rounded-lg border border-gray-200 dark:border-gray-700"
            alt="Photo preview">
        <p id="photo_filename" class="mt-2 text-xs text-gray-500 dark:text-gray-400"></p>
        <button type="button" id="photo_remove_btn"
            class="mt-2 text-sm text-red-600 hover:text-red-500 dark:text-red-400">
            Remove
        </button>
    </div>

    @error('photo')
        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
    @enderror
</div>

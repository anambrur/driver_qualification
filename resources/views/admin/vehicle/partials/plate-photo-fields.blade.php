{{-- Form section (vehicle and trailer dialogs): plate, plate state and photo. Needs $plateStates
     (country => [code => name]). The page passes dqCrud.photoPreview as afterFill. --}}
<div class="form-section">
    <h3 class="form-section-title">{{ svg('lucide-rectangle-horizontal', '', ['aria-hidden' => 'true']) }} Plate and photo</h3>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.field label="Plate number" name="plate_number">
            <x-ui.input name="plate_number" maxlength="20" autocomplete="off" spellcheck="false" class="font-mono uppercase tracking-wider" placeholder="ABC1234" />
        </x-ui.field>
        <x-ui.field label="Plate state" name="plate_state_code" hint="Required with a plate number.">
            <x-ui.select name="plate_state_code" placeholder="Select state or province">
                @foreach ($plateStates as $country => $states)
                    <optgroup label="{{ $country }}">
                        @foreach ($states as $code => $state)
                            <option value="{{ $code }}">{{ $state }} ({{ $code }})</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </x-ui.select>
        </x-ui.field>
        <x-ui.field label="Photo" name="photo" hint="JPG, PNG or WebP, up to 5 MB." class="sm:col-span-2">
            <x-ui.input type="file" name="photo" accept="image/jpeg,image/png,image/webp" />
        </x-ui.field>
        <div class="flex items-center gap-4 sm:col-span-2 lg:col-span-4" data-current-photo hidden>
            <img alt="Current photo" width="96" height="64" class="h-16 w-24 rounded-md border object-cover">
            <x-ui.checkbox name="remove_photo" label="Remove photo" description="Deletes the current photo when you save." />
        </div>
    </div>
</div>


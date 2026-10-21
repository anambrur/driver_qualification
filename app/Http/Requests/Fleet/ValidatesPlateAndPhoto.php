<?php

namespace App\Http\Requests\Fleet;

use App\Support\Fleet\PlateStates;

/**
 * Plate number, plate state and photo, shared by the vehicle and trailer forms (req 3.5).
 * Plates are not unique: they get reissued, and one number can exist in two states.
 */
trait ValidatesPlateAndPhoto
{
    protected function prepareForValidation(): void
    {
        foreach (['plate_number', 'plate_state_code'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => strtoupper($this->input($field))]);
            }
        }

        // Plate and state are one pair: sending either sends both, and no plate means no state
        if ($this->route('id') === null || $this->hasAny(['plate_number', 'plate_state_code'])) {
            $plate = $this->input('plate_number');
            $this->merge([
                'plate_number' => blank($plate) ? null : $plate,
                'plate_state_code' => blank($plate) ? null : $this->input('plate_state_code'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function plateAndPhotoRules(): array
    {
        return [
            'plate_number' => ['nullable', 'string', 'max:20'],
            'plate_state_code' => ['nullable', 'required_with:plate_number', 'string', 'size:2', PlateStates::rule()],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function plateAndPhotoMessages(): array
    {
        return [
            'plate_state_code.required_with' => 'Select the state or province that issued the plate.',
            'plate_state_code.exists' => 'Select a US state or Canadian province.',
            'photo.max' => 'The photo may not be larger than 5 MB.',
        ];
    }
}

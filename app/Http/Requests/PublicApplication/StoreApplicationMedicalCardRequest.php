<?php

namespace App\Http\Requests\PublicApplication;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationMedicalCardRequest extends FormRequest
{
    use ValidatesApplicationDriver;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => $this->applicationDriverRules(),
            'medical_card' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ];
    }
}

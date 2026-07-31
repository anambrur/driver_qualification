<?php

namespace App\Http\Requests\PublicApplication;

use App\Models\DriverDocument;
use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $existing = DriverDocument::where('driver_id', $this->input('driver_id'))->first();
        $frontRule = ($existing && $existing->license_front) ? 'nullable' : 'required';
        $backRule = ($existing && $existing->license_back) ? 'nullable' : 'required';

        return [
            'driver_id' => 'required|exists:drivers,id',
            'license_front' => $frontRule.'|image|mimes:jpg,jpeg,png,webp|max:5120',
            'license_back' => $backRule.'|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }
}

<?php

namespace App\Http\Requests\PublicApplication;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationDrugPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => 'required|exists:drivers,id',
            'employee_signature' => 'required|string|max:255',
            'date_signed' => 'required|date',
        ];
    }
}

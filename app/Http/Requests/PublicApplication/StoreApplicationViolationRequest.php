<?php

namespace App\Http\Requests\PublicApplication;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver_id' => 'required|exists:drivers,id',
            'violation' => 'required|in:no,yes',
            'violation_date' => 'sometimes|array',
            'violation_date.*' => 'nullable|date',
            'violation_location' => 'sometimes|array',
            'violation_location.*' => 'nullable|string|max:255',
            'offense' => 'sometimes|array',
            'offense.*' => 'nullable|string|max:255',
            'vehicle_type' => 'sometimes|array',
            'vehicle_type.*' => 'nullable|string|max:255',
            'applicant_signature' => 'required|string|max:255',
            'date_signed' => 'required|date',
        ];
    }
}

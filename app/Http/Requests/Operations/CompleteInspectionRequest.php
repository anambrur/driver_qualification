<?php

namespace App\Http\Requests\Operations;

use App\Models\Inspection;
use Illuminate\Foundation\Http\FormRequest;

class CompleteInspectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'odometer' => ['nullable', 'integer', 'min:0', 'max:'.Inspection::MAX_ODOMETER],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function odometer(): ?int
    {
        $odometer = $this->validated('odometer');

        return $odometer === null ? null : (int) $odometer;
    }
}

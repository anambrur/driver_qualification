<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class InspectionTemplateItemRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_required' => $this->boolean('is_required')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section' => ['required', 'string', 'max:100'],
            'label' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_required' => ['boolean'],
        ];
    }

    /**
     * @return array{section: string, label: string, sort_order: int, is_required: bool}
     */
    public function item(): array
    {
        return [
            'section' => trim((string) $this->validated('section')),
            'label' => trim((string) $this->validated('label')),
            'sort_order' => (int) $this->validated('sort_order'),
            'is_required' => (bool) $this->validated('is_required'),
        ];
    }
}

<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // One bullet per line in the form
        $this->merge([
            'is_public' => $this->boolean('is_public'),
            'is_active' => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
            'marketing_bullets' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->input('marketing_bullets')) ?: []))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('plans', 'slug')->ignore($this->route('plan'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'marketing_bullets' => ['array', 'max:20'],
            'marketing_bullets.*' => ['string', 'max:200'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function planAttributes(): array
    {
        return ['sort_order' => (int) $this->validated('sort_order')] + $this->safe()->except('sort_order');
    }
}

<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class TestimonialRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active'), 'remove_avatar' => $this->boolean('remove_avatar')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:150'],
            'quote' => ['required', 'string', 'max:1000'],
            'rating' => ['required', 'integer', 'between:1,5'],
            // Raster images only on the public disk
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:1024'],
            'remove_avatar' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function testimonialAttributes(): array
    {
        return ['sort_order' => (int) $this->validated('sort_order')] + $this->safe()->except(['sort_order', 'avatar', 'remove_avatar']);
    }
}

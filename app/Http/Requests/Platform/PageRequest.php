<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class PageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['is_published' => $this->boolean('is_published')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:300'],
            'body' => ['nullable', 'string', 'max:200000'],
            'is_published' => ['boolean'],
        ];
    }
}

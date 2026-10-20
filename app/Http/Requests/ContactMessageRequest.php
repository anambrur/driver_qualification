<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The Contact page form. Public and throttled; `website` is a honeypot.
 */
class ContactMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:2000'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function messageAttributes(): array
    {
        return $this->safe()->only(['name', 'email', 'company', 'phone', 'message']);
    }
}

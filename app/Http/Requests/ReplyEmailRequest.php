<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The inbox replies go to, for a company or the platform. The routes check the permission.
 */
class ReplyEmailRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reply_to_email' => ['required', 'email:rfc', 'max:255'],
        ];
    }
}

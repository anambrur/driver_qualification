<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Voiding an event needs a reason; the event and its files stay (08 §9).
 */
class VoidTripEventRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['void_reason' => ['required', 'string', 'max:500']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['void_reason' => 'reason'];
    }
}

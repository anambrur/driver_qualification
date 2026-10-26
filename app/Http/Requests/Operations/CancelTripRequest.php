<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancelling a trip needs a reason (10 §13); it is kept on the trip and in the audit log.
 */
class CancelTripRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['cancel_reason' => ['required', 'string', 'max:500']];
    }
}

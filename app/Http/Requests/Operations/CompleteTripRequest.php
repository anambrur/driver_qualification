<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Completing a trip: the actual miles are typed in (no GPS until Phase 3) and optional.
 */
class CompleteTripRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['actual_distance_miles' => ['nullable', 'numeric', 'min:0', 'max:9999999.9']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['actual_distance_miles' => 'actual miles'];
    }
}

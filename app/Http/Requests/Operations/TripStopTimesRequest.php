<?php

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Arrival and departure times of a trip in progress, keyed by stop id, in the company's time
 * zone. TripLifecycleService checks the stops belong to the trip and each stop's arrival and
 * departure (range and order).
 */
class TripStopTimesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stops' => ['required', 'array', 'max:3'],
            'stops.*' => ['array:arrived_at,departed_at'],
            'stops.*.arrived_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'stops.*.departed_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['stops.*.arrived_at' => 'arrival', 'stops.*.departed_at' => 'departure'];
    }
}

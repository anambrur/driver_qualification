<?php

namespace App\Http\Requests\Operations;

use App\Models\Trip;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a trip or edit its details (req 4.1–4.3). The number can be typed only on edit, and
 * TripService accepts it and the date only while they are unconfirmed. Whether the shipper is
 * active is checked there too, so a trip keeps a shipper that was later deactivated.
 */
class TripRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trip_number' => [$this->route('trip') === null ? 'prohibited' : 'nullable', 'integer', 'min:1', 'max:'.Trip::MAX_NUMBER],
            'trip_date' => ['nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'shipper_id' => ['nullable', 'integer', TenantRule::exists('shippers')],
            'product' => ['nullable', 'string', 'max:150'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'weight_unit' => ['required', Rule::in(array_keys(Trip::WEIGHT_UNITS))],
            'planned_distance_miles' => ['nullable', 'numeric', 'min:0', 'max:9999999.9'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['planned_distance_miles' => 'planned miles', 'shipper_id' => 'shipper'];
    }
}

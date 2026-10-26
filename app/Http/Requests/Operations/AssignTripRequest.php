<?php

namespace App\Http\Requests\Operations;

use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Assign a driver, vehicle and optional trailer (req 4.5). Every id must be of the company;
 * whether the driver is active and the assets available is checked by TripAssignmentService.
 * An empty vehicle takes the driver's asset group.
 */
class AssignTripRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', TenantRule::exists('drivers')],
            'vehicle_id' => ['nullable', 'integer', TenantRule::exists('vehicles')],
            'trailer_id' => ['nullable', 'integer', TenantRule::exists('trailers')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['driver_id' => 'driver', 'vehicle_id' => 'vehicle', 'trailer_id' => 'trailer'];
    }
}

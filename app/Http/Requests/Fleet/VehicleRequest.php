<?php

namespace App\Http\Requests\Fleet;

use App\Models\Vehicle;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add and edit a vehicle. The company is never posted: BelongsToCompany sets it on create, and
 * the platform's Asset Transfer is the only way to move a vehicle (PO-08).
 */
class VehicleRequest extends FormRequest
{
    use ValidatesPlateAndPhoto;

    private ?Vehicle $vehicle = null;

    /**
     * Another company's vehicle is a 404, and the policy runs, before validation (03 §5).
     */
    public function authorize(): bool
    {
        $vehicle = $this->vehicle();

        return $vehicle === null || $this->user()?->can('update', $vehicle) === true;
    }

    /**
     * The vehicle being edited (null on create), looked up once for authorize() and the controller.
     */
    public function vehicle(): ?Vehicle
    {
        if ($this->route('id') === null) {
            return null;
        }

        return $this->vehicle ??= Vehicle::query()->findOrFail($this->route('id'));
    }

    /**
     * Unit no and VIN are unique within the company, deleted vehicles included (the DB index).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ignore = $this->route('id');

        return [
            'unit_no' => ['required', 'string', 'max:50', TenantRule::unique('vehicles', 'unit_no')->whereNull('transferred_out_at')->ignore($ignore)],
            'vin' => ['required', 'string', 'size:17', 'not_regex:/[IOQ]/i', TenantRule::unique('vehicles', 'vin')->whereNull('transferred_out_at')->ignore($ignore)],
            'year' => 'required|integer|min:1900|max:'.(date('Y') + 1),
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'vehicle_type_id' => 'nullable|exists:vehicle_types,id',
            'owned_by' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'title_no' => 'nullable|string|max:100',
            'tire_size' => 'nullable|string|max:50',
            'odometer' => 'required|integer|min:0',
            'engine_hours' => 'nullable|integer|min:0',
            'gvw' => 'nullable|integer|min:0',
            'vehicle_group_id' => 'nullable|exists:vehicle_groups,id',
            'fuel_type_id' => 'nullable|exists:fuel_types,id',
            'engine_type' => 'nullable|string|max:100',
            'transmission' => 'nullable|string|max:100',
            'suspension' => 'nullable|string|max:100',
            'no_axles' => 'nullable|integer|min:1|max:10',
            'configuration' => 'nullable|in:conventional,cabover',
            'wheel_base' => 'nullable|integer|min:0',
            'size_dimension' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
        ] + $this->plateAndPhotoRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['vin.not_regex' => 'A VIN never contains the letters I, O or Q.'] + $this->plateAndPhotoMessages();
    }
}

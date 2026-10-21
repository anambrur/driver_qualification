<?php

namespace App\Http\Requests\Fleet;

use App\Models\Trailer;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add and edit a trailer. The company is never posted: BelongsToCompany sets it on create, and
 * the platform's Asset Transfer is the only way to move a trailer (PO-08).
 */
class TrailerRequest extends FormRequest
{
    use ValidatesPlateAndPhoto;

    private ?Trailer $trailer = null;

    /**
     * Another company's trailer is a 404, and the policy runs, before validation (03 §5).
     */
    public function authorize(): bool
    {
        $trailer = $this->trailer();

        return $trailer === null || $this->user()?->can('update', $trailer) === true;
    }

    /**
     * The trailer being edited (null on create), looked up once for authorize() and the controller.
     */
    public function trailer(): ?Trailer
    {
        if ($this->route('id') === null) {
            return null;
        }

        return $this->trailer ??= Trailer::query()->findOrFail($this->route('id'));
    }

    /**
     * Unit no and VIN are unique within the company, deleted trailers included (the DB index).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $ignore = $this->route('id');

        return [
            'unit_no' => ['required', 'string', 'max:50', TenantRule::unique('trailers', 'unit_no')->ignore($ignore)],
            'vin' => ['required', 'string', 'size:17', 'not_regex:/[IOQ]/i', TenantRule::unique('trailers', 'vin')->ignore($ignore)],
            'year' => 'required|integer|min:1900|max:'.(date('Y') + 1),
            'make' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'equipment_type_id' => 'required|exists:equipment_types,id',
            'owned_by' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'title_no' => 'nullable|string|max:100',
            'tire_size' => 'nullable|string|max:50',
            'gvw' => 'nullable|integer|min:0',
            'vehicle_group_id' => 'nullable|exists:vehicle_groups,id',
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

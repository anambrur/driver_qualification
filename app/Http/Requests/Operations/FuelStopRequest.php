<?php

namespace App\Http\Requests\Operations;

use App\Http\Requests\Operations\Concerns\ValidatesTripEventPlace;
use App\Models\FuelPurchase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * A fuel stop (req 7.6): where, the state, gallons, price per gallon, the typed-in total, the fuel
 * type (platform lookup), the odometer and optional receipts. A total off gallons × price is a
 * warning, not an error (FuelPurchase::totalMismatch).
 */
class FuelStopRequest extends FormRequest
{
    use ValidatesTripEventPlace;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->whenAndWhereRules(stateRequired: true),
            'location_name' => ['nullable', 'string', 'max:255'],
            'gallons' => ['required', 'numeric', 'gt:0', 'max:9999999.999', 'decimal:0,3'],
            'price_per_gallon' => ['required', 'numeric', 'gt:0', 'max:99999.999', 'decimal:0,3'],
            'total_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'fuel_type_id' => ['required', 'integer', Rule::exists('fuel_types', 'id')->where('is_active', true)],
            'odometer' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            ...$this->slotRules('receipts'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['fuel_type_id.exists' => 'Choose a fuel type from the list.', ...$this->uploadMessages('receipts')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['occurred_at' => 'time', 'fuel_state_code' => 'state', 'fuel_city' => 'city', 'fuel_address' => 'address', 'total_amount' => 'total', 'fuel_type_id' => 'fuel type'];
    }

    /** The trip event dialog's picker is on the same page, so these fields are fuel_address, … */
    protected function placePrefix(): string
    {
        return 'fuel_';
    }

    /**
     * @return list<UploadedFile>
     */
    public function receipts(): array
    {
        return array_values((array) $this->file('receipts', []));
    }

    public function totalMismatch(): bool
    {
        return FuelPurchase::totalMismatch((float) $this->validated('gallons'), (float) $this->validated('price_per_gallon'), (float) $this->validated('total_amount'));
    }
}

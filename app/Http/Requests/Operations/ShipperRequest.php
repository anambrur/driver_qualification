<?php

namespace App\Http\Requests\Operations;

use App\Http\Requests\Operations\Concerns\ValidatesPlace;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add and edit a shipper (req 4.4). The address rules are shared with trip stops (ValidatesPlace).
 */
class ShipperRequest extends FormRequest
{
    use ValidatesPlace;

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalisePlace($this->only(['state_code', 'country_code', 'zip', 'latitude', 'longitude', 'place_source', 'place_ref'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ] + $this->placeRules('', $this->input('country_code'), 'address_line1');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->placeMessages('', $this->input('country_code'));
    }
}

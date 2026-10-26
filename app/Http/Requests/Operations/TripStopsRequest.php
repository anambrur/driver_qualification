<?php

namespace App\Http\Requests\Operations;

use App\Http\Requests\Operations\Concerns\ValidatesPlace;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The planned route (req 6.1, 6.3): a start, a drop-off, and a pickup only when "has_pickup"
 * is ticked. Each stop is an address from the map picker with an optional shipper.
 */
class TripStopsRequest extends FormRequest
{
    use ValidatesPlace;

    private const TYPES = ['start', 'pickup', 'drop_off'];

    protected function prepareForValidation(): void
    {
        $stops = $this->input('stops');
        $stops = is_array($stops) ? $stops : [];

        if (! $this->boolean('has_pickup')) {
            unset($stops['pickup']);
        }

        foreach ($stops as $type => $stop) {
            if (in_array($type, self::TYPES, true) && is_array($stop)) {
                $stops[$type] = $this->normalisePlace($stop);
            }
        }

        $this->merge(['stops' => $stops]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'has_pickup' => ['nullable', 'boolean'],
            'stops' => ['required', 'array:'.implode(',', self::TYPES)],
            'stops.start' => ['required', 'array'],
            'stops.start.also_pickup' => ['nullable', 'boolean'],
            'stops.drop_off' => ['required', 'array'],
        ];

        foreach ($this->types() as $type) {
            $rules += $this->placeRules("stops.{$type}.", $this->input("stops.{$type}.country_code"), 'address_line')
                + ["stops.{$type}.shipper_id" => ['nullable', 'integer', TenantRule::exists('shippers')]];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return collect($this->types())
            ->flatMap(fn (string $type) => $this->placeMessages("stops.{$type}.", $this->input("stops.{$type}.country_code")))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect($this->types())->flatMap(fn (string $type) => [
            "stops.{$type}.address_line" => 'address',
            "stops.{$type}.city" => 'city',
            "stops.{$type}.state_code" => 'state or province',
            "stops.{$type}.zip" => 'ZIP or postal code',
            "stops.{$type}.country_code" => 'country',
            "stops.{$type}.shipper_id" => 'shipper',
        ])->all();
    }

    /**
     * The validated stops for TripService::saveStops().
     *
     * @return array{start: array<string, mixed>, pickup: array<string, mixed>|null, drop_off: array<string, mixed>}
     */
    public function stops(): array
    {
        $stops = $this->validated('stops');

        return ['start' => $stops['start'], 'pickup' => $stops['pickup'] ?? null, 'drop_off' => $stops['drop_off']];
    }

    /**
     * @return list<string>
     */
    private function types(): array
    {
        return $this->boolean('has_pickup') ? self::TYPES : ['start', 'drop_off'];
    }
}

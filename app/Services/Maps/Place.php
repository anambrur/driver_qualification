<?php

namespace App\Services\Maps;

/**
 * One geocoder result. Only source and ref are stored with an address (08 §9), never the raw
 * response; ref is the OpenStreetMap object (N, W or R plus the id).
 */
final readonly class Place
{
    public function __construct(
        public string $label,
        public ?string $addressLine1,
        public ?string $city,
        public ?string $stateCode,
        public ?string $zip,
        public string $countryCode,
        public float $lat,
        public float $lng,
        public string $source,
        public ?string $ref,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'address_line1' => $this->addressLine1,
            'city' => $this->city,
            'state_code' => $this->stateCode,
            'zip' => $this->zip,
            'country_code' => $this->countryCode,
            'latitude' => $this->lat,
            'longitude' => $this->lng,
            'place_source' => $this->source,
            'place_ref' => $this->ref,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['label'], $data['address_line1'], $data['city'], $data['state_code'], $data['zip'],
            $data['country_code'], (float) $data['latitude'], (float) $data['longitude'], $data['place_source'], $data['place_ref'],
        );
    }
}

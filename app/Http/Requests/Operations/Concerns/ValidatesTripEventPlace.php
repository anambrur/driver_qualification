<?php

namespace App\Http\Requests\Operations\Concerns;

use App\Http\Requests\Documents\UploadDocumentsRequest;
use App\Support\Fleet\PlateStates;

/**
 * When and where a trip event happened (req 7.1–7.6): the time in the company time zone and an
 * optional place from the map picker (no ZIP or country on trip_events), plus the upload rules of
 * one file slot.
 */
trait ValidatesTripEventPlace
{
    /** The place fields of trip_events; a form with a second picker on the page prefixes them. */
    private static array $placeFields = ['address', 'city', 'state_code', 'latitude', 'longitude'];

    /**
     * @return array<string, mixed>
     */
    protected function whenAndWhereRules(bool $stateRequired = false): array
    {
        $p = $this->placePrefix();

        return [
            'occurred_at' => ['required', 'date_format:Y-m-d\TH:i'],
            $p.'address' => ['nullable', 'string', 'max:255'],
            $p.'city' => ['nullable', 'string', 'max:100'],
            $p.'state_code' => [$stateRequired ? 'required' : 'nullable', 'string', 'size:2', PlateStates::rule()],
            $p.'latitude' => ['nullable', "required_with:{$p}longitude", 'numeric', 'between:-90,90'],
            $p.'longitude' => ['nullable', "required_with:{$p}latitude", 'numeric', 'between:-180,180'],
        ];
    }

    protected function placePrefix(): string
    {
        return '';
    }

    /**
     * The validated data with the place fields under their trip_events names.
     *
     * @param  list<string>  $except
     * @return array<string, mixed>
     */
    public function eventData(array $except = []): array
    {
        $data = $this->safe()->except($except);
        $p = $this->placePrefix();

        foreach (self::$placeFields as $field) {
            if ($p !== '' && array_key_exists($p.$field, $data)) {
                $data[$field] = $data[$p.$field];
                unset($data[$p.$field]);
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function slotRules(string $key): array
    {
        $types = implode(',', UploadDocumentsRequest::TYPES);

        return [
            $key => ['nullable', 'array', 'max:'.UploadDocumentsRequest::MAX_FILES],
            $key.'.*' => ['bail', 'required', 'file', UploadDocumentsRequest::refuseHeic(...), 'max:'.UploadDocumentsRequest::MAX_KB, "mimes:{$types}", "extensions:{$types}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function uploadMessages(string $key): array
    {
        $message = 'Upload a PDF, an image (JPG, PNG, WebP), a Word or Excel file, a CSV or a text file.';

        return [
            $key.'.max' => 'Upload up to '.UploadDocumentsRequest::MAX_FILES.' files at a time.',
            $key.'.*.max' => 'Each file can be up to 20 MB.',
            $key.'.*.mimes' => $message,
            $key.'.*.extensions' => $message,
            $this->placePrefix().'state_code.exists' => 'Select a US state or Canadian province.',
            $this->placePrefix().'latitude.required_with' => 'Enter both latitude and longitude, or neither.',
            $this->placePrefix().'longitude.required_with' => 'Enter both latitude and longitude, or neither.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $key = $this->placePrefix().'state_code';
        if (is_string($this->input($key))) {
            $this->merge([$key => strtoupper(trim($this->input($key))) ?: null]);
        }
    }
}

<?php

namespace App\Http\Requests\Operations;

use App\Http\Requests\Operations\Concerns\ValidatesTripEventPlace;
use App\Models\TripEvent;
use App\Services\Operations\TripEventService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * One trip event other than a fuel stop, with the fields of its type (the trip_events CHECKs) and
 * files by slot (files[ticket][], files[photos][], …). TripEventService checks the required files.
 */
class TripEventRequest extends FormRequest
{
    use ValidatesTripEventPlace;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'type' => ['required', Rule::in(array_keys(array_diff_key(TripEvent::TYPES, ['fuel' => true])))],
            ...$this->whenAndWhereRules(),
            'reason' => [Rule::requiredIf(fn () => $type === 'stop' || ($type === 'police_stop' && in_array($this->input('ticket_issued'), [false, 0, '0'], true))), 'nullable', 'string', 'max:2000'],
            'ticket_issued' => [Rule::requiredIf($type === 'police_stop'), 'nullable', 'boolean'],
            'weight_ok' => [Rule::requiredIf($type === 'weigh_station'), 'nullable', 'boolean'],
            'injuries_count' => [Rule::requiredIf($type === 'accident'), 'nullable', 'integer', 'min:0', 'max:999'],
            'fatalities_count' => [Rule::requiredIf($type === 'accident'), 'nullable', 'integer', 'min:0', 'max:999'],
            'hazmat_released' => [Rule::requiredIf($type === 'accident'), 'nullable', 'boolean'],
            'files' => ['nullable', 'array:'.implode(',', array_keys(TripEventService::SLOTS))],
            ...collect(array_keys(TripEventService::SLOTS))->flatMap(fn (string $slot) => $this->slotRules("files.{$slot}"))->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => $this->input('type') === 'stop' ? 'Give the reason for the stop.' : 'Give the reason when no ticket was issued.',
            'ticket_issued.required' => 'Say whether a ticket was issued.',
            'weight_ok.required' => 'Say whether the weight was OK.',
            'hazmat_released.required' => 'Say whether hazardous materials were released.',
            ...$this->uploadMessages('files.*'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['occurred_at' => 'time', 'injuries_count' => 'injuries', 'fatalities_count' => 'fatalities'];
    }

    /**
     * The uploads by slot.
     *
     * @return array<string, list<UploadedFile>>
     */
    public function slotFiles(): array
    {
        return array_map(fn ($files) => array_values((array) $files), (array) $this->file('files', []));
    }
}

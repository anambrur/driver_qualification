<?php

namespace App\Http\Requests\Operations;

use App\Http\Requests\Documents\UploadDocumentsRequest;
use App\Http\Requests\Operations\Concerns\ValidatesTripEventPlace;
use App\Services\Operations\TripEventService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Files added to a recorded event (late paperwork), into one of its type's slots.
 */
class TripEventAttachmentRequest extends FormRequest
{
    use ValidatesTripEventPlace;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'slot' => ['required', Rule::in(array_keys(TripEventService::SLOTS))],
            ...$this->slotRules('files'),
            'files' => ['required', 'array', 'max:'.UploadDocumentsRequest::MAX_FILES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['files.required' => 'Choose at least one file.', ...$this->uploadMessages('files')];
    }

    /**
     * @return list<UploadedFile>
     */
    public function uploads(): array
    {
        return array_values((array) $this->file('files', []));
    }
}

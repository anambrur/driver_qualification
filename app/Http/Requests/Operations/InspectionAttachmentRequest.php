<?php

namespace App\Http\Requests\Operations;

use App\Http\Requests\Documents\UploadDocumentsRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Photos or files for one failed item, with the trip folder's upload rules.
 */
class InspectionAttachmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = implode(',', UploadDocumentsRequest::TYPES);

        return [
            'template_item_id' => ['required', 'integer'],
            'files' => ['required', 'array', 'max:'.UploadDocumentsRequest::MAX_FILES],
            'files.*' => ['bail', 'required', 'file', 'max:'.UploadDocumentsRequest::MAX_KB, "mimes:{$types}", "extensions:{$types}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = 'Upload a PDF, an image (JPG, PNG, WebP), a Word or Excel file, a CSV or a text file.';

        return [
            'files.required' => 'Choose at least one photo or file.',
            'files.max' => 'Upload up to '.UploadDocumentsRequest::MAX_FILES.' files at a time.',
            'files.*.max' => 'Each file can be up to 20 MB.',
            'files.*.mimes' => $message,
            'files.*.extensions' => $message,
        ];
    }
}

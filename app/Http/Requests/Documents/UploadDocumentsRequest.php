<?php

namespace App\Http\Requests\Documents;

use App\Models\DocumentType;
use App\Models\Folder;
use App\Services\Documents\FolderBrowser;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * One or more files into a folder. The metadata applies to every file; a title only fits one.
 */
class UploadDocumentsRequest extends FormRequest
{
    public const TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];

    public const MAX_KB = 20 * 1024;

    public const MAX_FILES = 10;

    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->folder()) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = implode(',', self::TYPES);

        return [
            'files' => ['required', 'array', 'max:'.self::MAX_FILES],
            'files.*' => ['bail', 'required', 'file', self::refuseHeic(...), 'max:'.self::MAX_KB, "mimes:{$types}", "extensions:{$types}"],
            'title' => ['nullable', 'string', 'max:255', Rule::prohibitedIf(fn () => count((array) $this->file('files', [])) > 1)],
            'document_type_id' => ['nullable', 'integer', $this->enabledType(...)],
            'issued_on' => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = 'Upload a PDF, an image (JPG, PNG, WebP), a Word or Excel file, a CSV or a text file.';

        return [
            'files.required' => 'Choose at least one file.',
            'files.max' => 'Upload up to '.self::MAX_FILES.' files at a time.',
            'files.*.max' => 'Each file can be up to 20 MB.',
            'files.*.mimes' => $message,
            'files.*.extensions' => $message,
            'title.prohibited' => 'A title fits one file. With several files, each takes its file name.',
        ];
    }

    public function folder(): Folder
    {
        /** @var Folder */
        return $this->route('folder');
    }

    protected function getRedirectUrl(): string
    {
        return FolderBrowser::url($this->folder());
    }

    /**
     * Also used by the trip event uploads (ValidatesTripEventPlace).
     */
    public static function refuseHeic(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value instanceof UploadedFile && (in_array(strtolower($value->getClientOriginalExtension()), ['heic', 'heif'], true)
            || in_array($value->getMimeType(), ['image/heic', 'image/heif'], true))) {
            $fail('HEIC photos aren\'t supported yet. Export as JPG or PDF and upload again.');
        }
    }

    /**
     * An active type of the folder's module (any module for a company-level folder), not
     * switched off by the company.
     */
    private function enabledType(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = DocumentType::query()
            ->enabledForCompany(app(TenantContext::class)->requireCompanyId())
            ->byModule($this->folder()->module())
            ->whereKey($value)
            ->exists();

        if (! $exists) {
            $fail('Choose a document type from the list.');
        }
    }
}

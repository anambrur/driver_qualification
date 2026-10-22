<?php

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Models\Folder;
use App\Services\Documents\FolderBrowser;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class MoveDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->document()) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['folder_id' => ['required', 'integer', TenantRule::exists('folders')->whereNull('deleted_at')]];
    }

    public function document(): Document
    {
        /** @var Document */
        return $this->route('document');
    }

    public function target(): Folder
    {
        return Folder::query()->findOrFail((int) $this->validated('folder_id'));
    }

    protected function getRedirectUrl(): string
    {
        return $this->document()->folder === null ? route('documents.index') : FolderBrowser::url($this->document()->folder);
    }
}

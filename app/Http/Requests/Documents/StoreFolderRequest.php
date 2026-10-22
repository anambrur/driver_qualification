<?php

namespace App\Http\Requests\Documents;

use App\Models\Folder;
use App\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new folder below parent_id, or a company-level folder without one.
 */
class StoreFolderRequest extends FormRequest
{
    use FolderNameRules;

    private ?Folder $parent = null;

    public function authorize(): bool
    {
        $parent = $this->parent();

        return $parent === null || $this->user()?->can('view', $parent) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', TenantRule::exists('folders')->whereNull('deleted_at')],
            'name' => $this->nameRules(),
        ];
    }

    public function parent(): ?Folder
    {
        if (! $this->filled('parent_id')) {
            return null;
        }

        return $this->parent ??= Folder::query()->findOrFail((int) $this->input('parent_id'));
    }

    protected function getRedirectUrl(): string
    {
        return $this->folderRedirect($this->parent());
    }
}

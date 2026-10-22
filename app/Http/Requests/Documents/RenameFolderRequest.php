<?php

namespace App\Http\Requests\Documents;

use App\Models\Folder;
use Illuminate\Foundation\Http\FormRequest;

class RenameFolderRequest extends FormRequest
{
    use FolderNameRules;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->folder()) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['name' => $this->nameRules()];
    }

    public function folder(): Folder
    {
        /** @var Folder */
        return $this->route('folder');
    }

    protected function getRedirectUrl(): string
    {
        return $this->folderRedirect($this->folder());
    }
}

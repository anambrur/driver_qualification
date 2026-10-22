<?php

namespace App\Http\Requests\Documents;

use App\Models\Folder;
use App\Services\Documents\FolderBrowser;

/**
 * Folder names, and the way back to the open folder (and its dialog) when validation fails.
 */
trait FolderNameRules
{
    /**
     * @return list<string>
     */
    protected function nameRules(): array
    {
        // Folder names become ZIP paths (P1-6), so no slashes. Standard folder names are reserved:
        // a root folder named "Vehicle #812" would block the next vehicle's own folder.
        return ['required', 'string', 'max:120', 'not_regex:/[\/\\\\]/', 'not_regex:/^\s*(dq file|vehicle|trailer) #\d+\s*$/i'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.not_regex' => 'A folder name can\'t contain / or \\, or look like a standard folder name ("DQ file #12").'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    protected function folderRedirect(?Folder $folder): string
    {
        return FolderBrowser::url($folder);
    }
}

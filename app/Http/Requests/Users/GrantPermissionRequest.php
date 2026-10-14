<?php

namespace App\Http\Requests\Users;

use App\Authorization\PermissionCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permission' => ['required', 'string', Rule::in(array_keys(PermissionCatalogue::TENANT))],
        ];
    }
}

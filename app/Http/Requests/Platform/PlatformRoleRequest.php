<?php

namespace App\Http\Requests\Platform;

use App\Authorization\PermissionCatalogue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformRoleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')
                ->where('team_id', 0)->where('guard_name', 'platform')->ignore($this->route('role'))],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::in(PermissionCatalogue::platformNames())],
        ];
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return array_values($this->validated('permissions', []));
    }
}

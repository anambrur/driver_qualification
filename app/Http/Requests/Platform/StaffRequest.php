<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Uniqueness across both login tables is EmailIdentityService's job (D3a).
            'email' => ['required', 'email', 'max:255'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')->where('team_id', 0)->where('guard_name', 'platform')],
        ];
    }

    /**
     * @return list<int>
     */
    public function roleIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->validated('roles'))));
    }
}

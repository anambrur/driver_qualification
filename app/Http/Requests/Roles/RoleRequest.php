<?php

namespace App\Http\Requests\Roles;

use App\Authorization\PermissionCatalogue;
use App\Services\Authorization\RoleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create and update. Names are unique within the company's team; whether the actor may grant
 * the permissions is decided by RoleService (06 §8 rule 2).
 */
class RoleRequest extends FormRequest
{
    /**
     * On update, another company's role is a 404 before validation runs (03 §5).
     */
    public function authorize(RoleService $roles): bool
    {
        if ($this->route('role') !== null) {
            $roles->find($this->user(), (int) $this->route('role'));
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'min:2', 'max:255',
                Rule::unique('roles', 'name')
                    ->where('team_id', $this->user()->company_id)
                    ->where('guard_name', 'web')
                    ->ignore($this->route('role')),
            ],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::in(array_keys(PermissionCatalogue::TENANT))],
        ];
    }
}

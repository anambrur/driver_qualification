<?php

namespace App\Http\Requests\Users;

use App\Authorization\PermissionCatalogue;
use App\Services\Users\UserService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantPermissionRequest extends FormRequest
{
    /**
     * Another company's user is a 404 before validation runs (03 §5).
     */
    public function authorize(UserService $users): bool
    {
        $users->find($this->user(), (int) $this->route('user'));

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

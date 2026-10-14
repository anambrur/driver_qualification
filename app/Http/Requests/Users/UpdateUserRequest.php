<?php

namespace App\Http\Requests\Users;

use App\Services\Users\UserService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
     * Role ids are resolved inside the actor's team by RoleAssignmentService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'roles' => ['array'],
            'roles.*' => ['integer', 'distinct'],
        ];
    }
}

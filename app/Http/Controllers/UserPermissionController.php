<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\GrantPermissionRequest;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentService;
use App\Services\Users\UserService;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Direct per-user grants (06 §8 rule 2), from the Extra permissions card on the user edit page.
 */
class UserPermissionController extends Controller
{
    public function __construct(
        private UserService $users,
        private RoleAssignmentService $assignments,
    ) {}

    public function store(GrantPermissionRequest $request, int $user): RedirectResponse
    {
        $actor = $this->actor($request);

        $this->assignments->grantDirect($actor, $this->users->find($actor, $user), $request->validated('permission'));

        Toast::success('Permission granted.');

        return back();
    }

    public function destroy(Request $request, int $user, string $permission): RedirectResponse
    {
        $actor = $this->actor($request);

        $this->assignments->revokeDirect($actor, $this->users->find($actor, $user), $permission);

        Toast::success('Permission revoked.');

        return back();
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\GrantPermissionRequest;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentService;
use App\Services\Users\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Direct per-user grants (06 §8 rule 2). Backend only; the UI comes in Phase 1.6.
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

        toastr()->success('Permission granted.');

        return back();
    }

    public function destroy(Request $request, int $user, string $permission): RedirectResponse
    {
        $actor = $this->actor($request);

        $this->assignments->revokeDirect($actor, $this->users->find($actor, $user), $permission);

        toastr()->success('Permission revoked.');

        return back();
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}

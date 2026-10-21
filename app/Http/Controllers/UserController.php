<?php

namespace App\Http\Controllers;

use App\Authorization\PermissionCatalogue;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\Authorization\RoleService;
use App\Services\Users\InvitationService;
use App\Services\Users\UserService;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Users of the logged-in user's own company (06 §8, 10 §5). Routes carry the permission
 * middleware; the services re-check and resolve ids inside the company.
 */
class UserController extends Controller
{
    public function __construct(
        private UserService $users,
        private RoleService $roles,
    ) {}

    public function index(Request $request, InvitationService $invitations): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        return view('admin.users.index', [
            'users' => $this->users->list($this->actor($request), $filters),
            'roles' => $this->roles->list($this->actor($request)),
            'invitations' => $request->user()->can('users.create') ? $invitations->pending($this->actor($request)) : collect(),
            'ownerId' => (int) $this->actor($request)->company->owner_user_id,
        ]);
    }

    public function edit(Request $request, int $user): View
    {
        $actor = $this->actor($request);
        $target = $this->users->find($actor, $user)->load('roles:id,name,system_key');
        $ownerId = (int) $actor->company->owner_user_id;

        // Extra permissions card (06 §8 rule 2): not on your own page or the Owner's.
        $grantTarget = ! $target->is($actor) && (int) $target->id !== $ownerId;
        $canGrant = $grantTarget && $actor->can('permissions.grant');
        $grantable = [];
        $held = [];

        if ($canGrant) {
            $target->load('roles.permissions:id,name', 'permissions:id,name');
            $grantable = array_merge(...array_values($this->roles->grantable($actor)));
            $held = $target->getAllPermissions()->pluck('name')->all();
        }

        return view('admin.users.edit', [
            'user' => $target,
            'roles' => $this->roles->assignable($actor),
            'canGrant' => $canGrant,
            'grantable' => $grantable,
            'grantGroups' => PermissionCatalogue::tenantGroups(array_diff($grantable, $held)),
            'showUpgradeHint' => $grantTarget && (int) $actor->id === $ownerId && ! $this->roles->customRolesAllowed($actor),
        ]);
    }

    public function update(UpdateUserRequest $request, int $user): RedirectResponse
    {
        $data = $request->validated();
        $actor = $this->actor($request);

        $this->users->update($actor, $this->users->find($actor, $user), $data['name'], array_map('intval', $data['roles'] ?? []));

        Toast::success('User updated successfully!');

        return redirect()->route('users.index');
    }

    public function deactivate(Request $request, int $user): RedirectResponse
    {
        return $this->setStatus($request, $user, 'inactive', 'User deactivated.');
    }

    public function reactivate(Request $request, int $user): RedirectResponse
    {
        return $this->setStatus($request, $user, 'active', 'User reactivated.');
    }

    private function setStatus(Request $request, int $user, string $status, string $message): RedirectResponse
    {
        $actor = $this->actor($request);

        $this->users->setStatus($actor, $this->users->find($actor, $user), $status);

        Toast::success($message);

        return redirect()->route('users.index');
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}

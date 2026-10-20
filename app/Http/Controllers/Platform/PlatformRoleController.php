<?php

namespace App\Http\Controllers\Platform;

use App\Authorization\PermissionCatalogue;
use App\Authorization\RoleTemplates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\PlatformRoleRequest;
use App\Models\PlatformUser;
use App\Services\Platform\PlatformRoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Platform roles (04 §3): built-in roles can be edited and reset, custom roles created and deleted.
 */
class PlatformRoleController extends Controller
{
    public function __construct(private PlatformRoleService $roles) {}

    public function index(): View
    {
        return view('platform.roles.index', ['roles' => $this->roles->list(), 'total' => count(PermissionCatalogue::PLATFORM)]);
    }

    public function create(): View
    {
        return view('platform.roles.form', ['role' => null, 'held' => [], 'groups' => PermissionCatalogue::platformGroups(), 'locked' => false, 'staffCount' => 0]);
    }

    public function store(PlatformRoleRequest $request): RedirectResponse
    {
        try {
            $this->roles->create($this->actor($request), $request->validated('name'), $request->validated('description'), $request->permissions());
        } catch (AuthorizationException $e) {
            return back()->withInput()->withErrors(['permissions' => $e->getMessage() ?: 'You cannot create this role.']);
        }

        return redirect()->route('platform.roles.index')->with('success', 'Role created. Assign it to staff from Staff.');
    }

    public function edit(int $role): View
    {
        $model = $this->roles->find($role);

        return view('platform.roles.form', [
            'role' => $model,
            'held' => $model->permissions->pluck('name')->all(),
            'groups' => PermissionCatalogue::platformGroups(),
            'locked' => $this->roles->isOwnerRole($model),
            'defaults' => $model->is_system ? RoleTemplates::platformPermissionsFor((string) $model->system_key) : null,
            'staffCount' => $this->roles->staffCount($model),
        ]);
    }

    public function update(PlatformRoleRequest $request, int $role): RedirectResponse
    {
        try {
            $this->roles->update($this->actor($request), $role, $request->validated('name'), $request->validated('description'), $request->permissions());
        } catch (AuthorizationException $e) {
            return back()->withInput()->withErrors(['permissions' => $e->getMessage() ?: 'This change is not allowed.']);
        }

        return redirect()->route('platform.roles.edit', $role)->with('success', 'Role saved. Staff with this role get the change on their next page.');
    }

    public function reset(Request $request, int $role): RedirectResponse
    {
        try {
            $this->roles->reset($this->actor($request), $role);
        } catch (AuthorizationException $e) {
            return back()->withErrors(['permissions' => $e->getMessage() ?: 'This change is not allowed.']);
        }

        return back()->with('success', 'Role reset to its default permissions.');
    }

    public function destroy(Request $request, int $role): RedirectResponse
    {
        try {
            $this->roles->delete($this->actor($request), $role);
        } catch (AuthorizationException $e) {
            return back()->withErrors(['role' => $e->getMessage() ?: 'This role cannot be deleted.']);
        }

        return redirect()->route('platform.roles.index')->with('success', 'Role deleted.');
    }

    private function actor(Request $request): PlatformUser
    {
        /** @var PlatformUser */
        return $request->user('platform');
    }
}

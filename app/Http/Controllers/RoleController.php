<?php

namespace App\Http\Controllers;

use App\Authorization\PermissionCatalogue;
use App\Http\Requests\Roles\RoleRequest;
use App\Models\User;
use App\Services\Authorization\RoleService;
use App\Support\Ui\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Roles of the logged-in user's company (10 §4). Every role lives in the company's team, so
 * another company's role id is a 404 and editing a role never affects another company.
 */
class RoleController extends Controller
{
    public function __construct(private RoleService $roles) {}

    public function index(Request $request): View
    {
        $roles = $this->roles->list($this->actor($request));

        return view('admin.roles.index', [
            'roles' => $roles,
            'manageable' => $roles->filter(fn ($role) => $this->roles->manageable($this->actor($request), $role))->modelKeys(),
            'customRoles' => $this->roles->customRolesAllowed($this->actor($request)),
            'total' => count(PermissionCatalogue::TENANT),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.roles.form', [
            'role' => null,
            'groups' => PermissionCatalogue::tenantGroups(array_merge(...array_values($this->roles->grantable($this->actor($request))))),
            'defaults' => null,
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->roles->create($this->actor($request), $data['name'], array_values($data['permissions'] ?? []));

        Toast::success('Role created successfully');

        return redirect()->route('admin.roles.index');
    }

    /**
     * Read-only: every permission of the role, for locked roles and roles the actor can't edit.
     * Another company's role is a 404.
     */
    public function show(Request $request, int $role): View|RedirectResponse
    {
        $model = $this->roles->find($this->actor($request), $role);

        if ($this->actor($request)->can('roles.edit') && $this->roles->manageable($this->actor($request), $model)) {
            return redirect()->route('admin.roles.edit', $role);
        }

        return view('admin.roles.show', [
            'role' => $model,
            'groups' => PermissionCatalogue::tenantGroups($model->permissions->pluck('name')->sort()),
        ]);
    }

    public function edit(Request $request, int $role): View
    {
        $model = $this->roles->find($this->actor($request), $role);

        abort_unless($this->roles->manageable($this->actor($request), $model), 403, 'This role cannot be changed.');

        return view('admin.roles.form', [
            'role' => $model,
            'groups' => PermissionCatalogue::tenantGroups(array_merge(...array_values($this->roles->grantable($this->actor($request))))),
            'defaults' => $this->roles->defaults($model),
        ]);
    }

    public function update(RoleRequest $request, int $role): RedirectResponse
    {
        $data = $request->validated();

        $this->roles->update($this->actor($request), $role, $data['name'], array_values($data['permissions'] ?? []));

        Toast::success('Role updated successfully');

        return redirect()->route('admin.roles.index');
    }

    public function reset(Request $request, int $role): RedirectResponse
    {
        $this->roles->reset($this->actor($request), $role);

        Toast::success('Role reset to its default permissions');

        return redirect()->route('admin.roles.edit', $role);
    }

    public function destroy(Request $request, int $role): RedirectResponse
    {
        $this->roles->delete($this->actor($request), $role);

        Toast::success('Role deleted successfully');

        return redirect()->route('admin.roles.index');
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}

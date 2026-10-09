<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

use function Flasher\Toastr\Prime\toastr;

class RoleController extends Controller
{
    /**
     * Roles the code checks by name (hasRole / role: middleware / registration). Renaming or
     * deleting them locks users out.
     */
    public const SYSTEM_ROLES = ['super-admin', 'company'];

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return view('admin.roles.index', ['roles' => $roles, 'systemRoles' => self::SYSTEM_ROLES]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permissions = Permission::all()->groupBy(function ($permission) {
            return Str::before($permission->name, '.');
        });
        return view('admin.roles.create', ['permissions' => $permissions]);
    }

    /**
     * Store a newly created resource in storage.
     */

    public function store(Request $request)
    {

        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string|min:2|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        DB::beginTransaction();

        try {
            // Create the role
            $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

            // Sync permissions if any were selected
            if (!empty($validated['permissions'])) {
                $permissions = Permission::whereIn('name', $validated['permissions'])->pluck('id');
                $role->syncPermissions($permissions);
            }

            DB::commit();

            toastr()->success('Role created successfully');

            return redirect()->route('admin.roles.index');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create role', ['exception' => $e]);
            toastr()->error('Failed to create role. Please try again.');

            return back()->withInput();
        }
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return redirect()->route('admin.roles.edit', $id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        $permissions = Permission::all()
            ->groupBy(function ($permission) {
                return Str::before($permission->name, '.');
            })
            ->map(function ($group) {
                return $group->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'guard_name' => $permission->guard_name,
                        'created_at' => $permission->created_at->toDateTimeString(),
                        'updated_at' => $permission->updated_at->toDateTimeString(),
                    ];
                });
            });

        return view('admin.roles.edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'), // Just get permission names
            ],
            'permissions' => $permissions,
            'permissionsLocked' => $role->name === 'super-admin',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Validate the request
        $validated = $request->validate([
            'name' => 'required|string|min:2|unique:roles,name,' . $id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $role = Role::findOrFail($id);

        if (in_array($role->name, self::SYSTEM_ROLES, true) && $validated['name'] !== $role->name) {
            toastr()->error('System roles cannot be renamed.');
            return back()->withInput();
        }

        DB::beginTransaction();

        try {
            // Update role name
            $role->name = $validated['name'];
            $role->save();

            // Sync permissions (empty array will remove all permissions).
            // super-admin always keeps every permission, otherwise admins can lock themselves out.
            $permissions = $role->name === 'super-admin'
                ? Permission::pluck('id')
                : Permission::whereIn('name', $validated['permissions'] ?? [])->pluck('id');
            $role->syncPermissions($permissions);

            DB::commit();

            toastr()->success('Role updated successfully');

            return redirect()->route('admin.roles.index');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update role', ['role_id' => $role->id, 'exception' => $e]);
            toastr()->error('Failed to update role. Please try again.');

            return back()->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::findOrFail($id);

        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            toastr()->error('System roles cannot be deleted.');
            return redirect()->route('admin.roles.index');
        }

        $role->delete();

        toastr()->success('Role deleted successfully');
        return redirect()->route('admin.roles.index');
    }
}

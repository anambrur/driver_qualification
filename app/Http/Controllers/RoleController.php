<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

use function Flasher\Toastr\Prime\toastr;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return view('admin.roles.index', ['roles' => $roles]);
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

            toastr()->error('Failed to create role: ' . $e->getMessage());

            return back()->withInput();
        }
    }



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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

        DB::beginTransaction();

        try {
            // Find the role
            $role = Role::findOrFail($id);

            // Update role name
            $role->name = $validated['name'];
            $role->save();

            // Sync permissions (empty array will remove all permissions)
            $permissions = Permission::whereIn('name', $validated['permissions'] ?? [])->pluck('id');
            $role->syncPermissions($permissions);

            DB::commit();

            toastr()->success('Role updated successfully');

            return redirect()->route('admin.roles.index');
        } catch (\Exception $e) {
            DB::rollBack();

            toastr()->error('Failed to update role: ' . $e->getMessage());

            return back()->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::findOrFail($id);
        $role->delete();
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::with('roles');

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($role = $request->get('role')) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // Filter by status
        if ($request->get('status') === 'suspended') {
            $query->where('status', 'inactive');
        } elseif ($request->get('status') === 'active') {
            $query->where('status', 'active');
        }

        $users = $query->latest()->paginate(15)->withQueryString();
        $roles = Role::all();

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Show form for creating new user.
     */
    public function create()
    {
        $roles = Role::all();
        return view('admin.users.create', compact('roles'));
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $this->authorizeSuperAdminChanges(null, (array) $request->input('roles', []));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => 'active',
        ]);

        if (!empty($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        toastr()->success('User created successfully!');
        return redirect()->route('users.index');
    }

    /**
     * Show form for editing user.
     */
    public function edit($id)
    {
        $user = User::with('roles')->findOrFail($id);
        $roles = Role::all();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $this->authorizeSuperAdminChanges($user, (array) $request->input('roles', []));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,id'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($user->id === auth()->id() && $validated['status'] === 'inactive') {
            toastr()->error('You cannot change your own status!');
            return back()->withInput();
        }

        if ($user->id === auth()->id() && isset($validated['roles']) && $user->hasRole('super-admin')
            && ! in_array((string) Role::where('name', 'super-admin')->value('id'), array_map('strval', $validated['roles']), true)) {
            toastr()->error('You cannot remove your own super-admin role!');
            return back()->withInput();
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->status = $validated['status'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        if (isset($validated['roles'])) {
            $user->roles()->sync($validated['roles']);
        }

        toastr()->success('User updated successfully!');
        return redirect()->route('users.index');
    }

    /**
     * Remove the specified user.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $this->authorizeSuperAdminChanges($user);

        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            toastr()->error('You cannot delete yourself!');
            return redirect()->route('users.index');
        }

        $user->delete();

        toastr()->success('User deleted successfully!');
        return redirect()->route('users.index');
    }

    /**
     * Suspend the specified user (same `inactive` status the edit form sets).
     */
    public function suspend($id)
    {
        return $this->setStatus($id, 'inactive', 'User suspended successfully!');
    }

    /**
     * Reactivate the specified user.
     */
    public function unsuspend($id)
    {
        return $this->setStatus($id, 'active', 'User reactivated successfully!');
    }

    private function setStatus($id, string $status, string $message)
    {
        $user = User::findOrFail($id);
        $this->authorizeSuperAdminChanges($user);

        if ($user->id === auth()->id()) {
            toastr()->error('You cannot change your own status!');
            return redirect()->route('users.index');
        }

        $user->status = $status;
        $user->save();

        toastr()->success($message);
        return redirect()->route('users.index');
    }

    /**
     * Only a super-admin may grant the super-admin role or change an existing super-admin.
     */
    private function authorizeSuperAdminChanges(?User $target, array $roleIds = []): void
    {
        if (auth()->user()->hasRole('super-admin')) {
            return;
        }

        $superAdminRoleId = Role::where('name', 'super-admin')->value('id');

        abort_if(
            ($target && $target->hasRole('super-admin')) || in_array((string) $superAdminRoleId, array_map('strval', $roleIds), true),
            403
        );
    }
}

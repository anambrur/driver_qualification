<?php

namespace App\Services\Authorization;

use App\Authorization\PermissionCatalogue;
use App\Authorization\TeamScope;
use App\Authorization\TenantPermissionResolver;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Company roles (06 §8): same company only, grant only what you hold, locked roles untouched.
 * Role ids are looked up inside the actor's team, so a foreign id is a 404.
 */
class RoleService
{
    public function __construct(private TenantPermissionResolver $resolver) {}

    /**
     * @param  list<string>  $permissions
     */
    public function create(User $actor, string $name, array $permissions): Role
    {
        $this->requirePermission($actor, 'roles.create');
        $this->requireHeld($actor, $permissions);

        return DB::transaction(fn () => TeamScope::run($actor->company_id, function () use ($actor, $name, $permissions) {
            /** @var Role $role */
            $role = Role::create(['name' => $name, 'guard_name' => 'web', 'team_id' => $actor->company_id]);
            $role->syncPermissions($permissions);

            AuditLog::record($actor, $actor->company_id, 'role.created', $role, ['name' => $name, 'permissions' => $permissions]);

            return $role;
        }));
    }

    /**
     * @param  list<string>  $permissions
     */
    public function update(User $actor, int $roleId, string $name, array $permissions): Role
    {
        $this->requirePermission($actor, 'roles.edit');

        return DB::transaction(fn () => TeamScope::run($actor->company_id, function () use ($actor, $roleId, $name, $permissions) {
            $role = $this->editableRole($actor, $roleId);
            $before = $role->permissions()->pluck('name')->all();

            if ($role->is_system && $name !== $role->name) {
                throw new AuthorizationException('System roles keep their name.');
            }

            $this->requireHeld($actor, array_merge($before, $permissions));

            $role->forceFill(['name' => $name])->save();
            $role->syncPermissions($permissions);
            $this->resolver->flush();

            AuditLog::record($actor, $actor->company_id, 'role.updated', $role, [
                'name' => $name,
                'added' => array_values(array_diff($permissions, $before)),
                'removed' => array_values(array_diff($before, $permissions)),
            ]);

            return $role;
        }));
    }

    public function delete(User $actor, int $roleId): void
    {
        $this->requirePermission($actor, 'roles.delete');

        DB::transaction(fn () => TeamScope::run($actor->company_id, function () use ($actor, $roleId) {
            $role = $this->editableRole($actor, $roleId);
            $this->requireHeld($actor, $role->permissions()->pluck('name')->all());

            if ($role->is_system && DB::table('model_has_roles')->where('team_id', $actor->company_id)->where('role_id', $role->id)->exists()) {
                throw new AuthorizationException('This role is still assigned to users.');
            }

            AuditLog::record($actor, $actor->company_id, 'role.deleted', $role, ['name' => $role->name]);

            $role->delete();
            $this->resolver->flush();
        }));
    }

    private function editableRole(User $actor, int $roleId): Role
    {
        $role = Role::query()
            ->where('team_id', $actor->company_id)
            ->where('guard_name', 'web')
            ->lockForUpdate()
            ->findOrFail($roleId);

        if ($role->isLocked()) {
            throw new AuthorizationException('This role cannot be changed.');
        }

        return $role;
    }

    private function requirePermission(User $actor, string $permission): void
    {
        if (! $this->resolver->has($actor, $permission)) {
            throw new AuthorizationException;
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function requireHeld(User $actor, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if (! isset(PermissionCatalogue::TENANT[$permission])) {
                throw new AuthorizationException("Unknown permission [{$permission}].");
            }
        }

        if (! $this->resolver->holdsAll($actor, $permissions)) {
            throw new AuthorizationException('You can only grant permissions you hold.');
        }
    }
}

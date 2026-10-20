<?php

namespace App\Services\Platform;

use App\Authorization\PermissionCatalogue;
use App\Authorization\RoleTemplates;
use App\Authorization\TeamScope;
use App\Authorization\TenantPermissionResolver;
use App\Models\AuditLog;
use App\Models\PlatformUser;
use App\Models\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Platform roles in team 0 (04 §3, 06 §8 rule 9): custom roles, and edits to the built-in
 * ones. Platform Owner always holds every permission and is never edited. Grant only what you
 * hold; every change is audited.
 */
class PlatformRoleService
{
    public function __construct(private TenantPermissionResolver $resolver) {}

    /**
     * Built-in roles first, with permission and staff counts.
     *
     * @return Collection<int, Role>
     */
    public function list(): Collection
    {
        return $this->roles()
            ->withCount('permissions')
            ->addSelect(['staff_count' => DB::table('model_has_roles')
                ->selectRaw('count(*)')
                ->whereColumn('model_has_roles.role_id', 'roles.id')
                ->where('model_has_roles.team_id', 0)])
            ->orderByDesc('is_system')
            ->orderBy('id')
            ->get();
    }

    public function find(int $roleId): Role
    {
        return $this->roles()->with('permissions:id,name')->findOrFail($roleId);
    }

    public function isOwnerRole(Role $role): bool
    {
        return $role->system_key === RoleTemplates::PLATFORM_OWNER;
    }

    public function staffCount(Role $role): int
    {
        return DB::table('model_has_roles')->where('team_id', 0)->where('role_id', $role->id)->count();
    }

    /**
     * @param  list<string>  $permissions
     */
    public function create(PlatformUser $actor, string $name, ?string $description, array $permissions): Role
    {
        $this->authorize($actor);
        $this->requireHeld($actor, $permissions);

        return DB::transaction(fn () => TeamScope::run(0, function () use ($actor, $name, $description, $permissions) {
            /** @var Role $role */
            $role = Role::create(['name' => $name, 'description' => $description, 'guard_name' => 'platform', 'team_id' => 0]);
            $role->syncPermissions($permissions);

            AuditLog::record($actor, null, 'platform_role.created', $role, ['name' => $name, 'permissions' => $permissions]);

            return $role;
        }));
    }

    /**
     * A built-in role keeps its name and is marked customized, so PlatformRoleSeeder keeps the edit.
     *
     * @param  list<string>  $permissions
     */
    public function update(PlatformUser $actor, int $roleId, string $name, ?string $description, array $permissions): void
    {
        $this->authorize($actor);

        DB::transaction(fn () => TeamScope::run(0, function () use ($actor, $roleId, $name, $description, $permissions) {
            $role = $this->editable($roleId);
            $before = $role->permissions()->pluck('name')->all();
            $this->requireHeld($actor, array_merge($before, $permissions));

            $added = array_values(array_diff($permissions, $before));
            $removed = array_values(array_diff($before, $permissions));

            $role->forceFill([
                'name' => $role->is_system ? $role->name : $name,
                'description' => $description,
                'customized_at' => $role->is_system && ($added || $removed) ? now() : $role->customized_at,
            ])->save();
            $role->syncPermissions($permissions);
            $this->resolver->flush();

            AuditLog::record($actor, null, 'platform_role.updated', $role, ['name' => $role->name, 'added' => $added, 'removed' => $removed]);
        }));
    }

    /**
     * Back to the built-in permissions from RoleTemplates; the seeder syncs it again from then on.
     */
    public function reset(PlatformUser $actor, int $roleId): void
    {
        $this->authorize($actor);

        DB::transaction(fn () => TeamScope::run(0, function () use ($actor, $roleId) {
            $role = $this->editable($roleId);

            if (! $role->is_system) {
                throw new AuthorizationException('Only built-in roles have defaults.');
            }

            $defaults = RoleTemplates::platformPermissionsFor((string) $role->system_key);
            $this->requireHeld($actor, array_merge($role->permissions()->pluck('name')->all(), $defaults));

            $role->forceFill(['customized_at' => null])->save();
            $role->syncPermissions($defaults);
            $this->resolver->flush();

            AuditLog::record($actor, null, 'platform_role.reset', $role, ['name' => $role->name]);
        }));
    }

    /**
     * Custom roles only, and only once no staff member holds it.
     */
    public function delete(PlatformUser $actor, int $roleId): void
    {
        $this->authorize($actor);

        DB::transaction(fn () => TeamScope::run(0, function () use ($actor, $roleId) {
            $role = $this->editable($roleId);

            if ($role->is_system) {
                throw new AuthorizationException('Built-in roles cannot be deleted.');
            }

            if ($this->staffCount($role) > 0) {
                throw new AuthorizationException('Remove this role from every staff member first.');
            }

            $this->requireHeld($actor, $role->permissions()->pluck('name')->all());

            AuditLog::record($actor, null, 'platform_role.deleted', $role, ['name' => $role->name]);

            $role->delete();
            $this->resolver->flush();
        }));
    }

    /**
     * @return Builder<Role>
     */
    private function roles(): Builder
    {
        return Role::query()->where('team_id', 0)->where('guard_name', 'platform');
    }

    private function editable(int $roleId): Role
    {
        $role = $this->roles()->lockForUpdate()->findOrFail($roleId);

        if ($this->isOwnerRole($role)) {
            throw new AuthorizationException('Platform Owner always has every permission.');
        }

        return $role;
    }

    private function authorize(PlatformUser $actor): void
    {
        if (! $this->resolver->has($actor, 'platform.staff.manage')) {
            throw new AuthorizationException;
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function requireHeld(PlatformUser $actor, array $permissions): void
    {
        foreach ($permissions as $permission) {
            if (! isset(PermissionCatalogue::PLATFORM[$permission])) {
                throw new AuthorizationException("Unknown permission [{$permission}].");
            }
        }

        if (! $this->resolver->holdsAll($actor, $permissions)) {
            throw new AuthorizationException('You can only grant permissions you hold.');
        }
    }
}

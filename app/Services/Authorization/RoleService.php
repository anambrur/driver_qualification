<?php

namespace App\Services\Authorization;

use App\Authorization\PermissionCatalogue;
use App\Authorization\RoleTemplates;
use App\Authorization\TeamScope;
use App\Authorization\TenantPermissionResolver;
use App\Features\Feature;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Entitlements\Entitlements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Company roles (06 §8): same company only, grant only what you hold, locked roles untouched.
 * Role ids are looked up inside the actor's team, so a foreign id is a 404. Built-in roles can be
 * edited on any plan (marked customized_at, so CompanyRoleSync keeps the edit) and reset; custom
 * roles need the Custom roles feature.
 */
class RoleService
{
    public function __construct(private TenantPermissionResolver $resolver) {}

    /**
     * The company's roles, tenant-assignable ones only (Support roles belong to support sessions).
     *
     * @return Collection<int, Role>
     */
    public function list(User $actor): Collection
    {
        return $this->team($actor)
            ->where(fn ($query) => $query->whereNull('system_key')->orWhereIn('system_key', ['owner', 'driver']))
            ->addSelect(['in_use' => DB::table('model_has_roles')
                ->selectRaw('1')
                ->whereColumn('model_has_roles.role_id', 'roles.id')
                ->whereColumn('model_has_roles.team_id', 'roles.team_id')
                ->limit(1)])
            ->addSelect(['users_count' => DB::table('model_has_roles')
                ->selectRaw('count(*)')
                ->whereColumn('model_has_roles.role_id', 'roles.id')
                ->whereColumn('model_has_roles.team_id', 'roles.team_id')])
            ->with('permissions:id,name')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    public function find(User $actor, int $roleId): Role
    {
        return $this->team($actor)->with('permissions:id,name')->findOrFail($roleId);
    }

    /**
     * Whether the actor may edit or delete the role: unlocked, they hold all its permissions,
     * and a custom role also needs the Custom roles feature.
     */
    public function manageable(User $actor, Role $role): bool
    {
        return ! $role->isLocked()
            && ($role->is_system || $this->customRolesAllowed($actor))
            && $this->resolver->holdsAll($actor, $role->permissions->pluck('name'));
    }

    /**
     * The company's plan includes Custom roles: creating, deleting and editing custom roles.
     */
    public function customRolesAllowed(User $actor): bool
    {
        return $actor->company !== null && Entitlements::for($actor->company)->allows(Feature::CustomRoles);
    }

    /**
     * The built-in permissions of a system role, or null for a custom one.
     *
     * @return list<string>|null
     */
    public function defaults(Role $role): ?array
    {
        return $role->is_system ? RoleTemplates::permissionsFor($role->name) : null;
    }

    /**
     * Unlocked roles whose permissions the actor holds (06 §8 rule 3), for invite and edit forms.
     *
     * @return Collection<int, Role>
     */
    public function assignable(User $actor): Collection
    {
        return $this->team($actor)
            ->whereNull('system_key')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role) => $this->manageable($actor, $role))
            ->values();
    }

    /**
     * Catalogue permissions the actor holds, grouped by module, for the role form (rule 2).
     *
     * @return array<string, list<string>>
     */
    public function grantable(User $actor): array
    {
        $groups = [];

        foreach (array_keys(PermissionCatalogue::TENANT) as $permission) {
            if ($this->resolver->has($actor, $permission)) {
                $groups[Str::before($permission, '.')][] = $permission;
            }
        }

        return $groups;
    }

    /**
     * The names that can work under the company's plan, for the per-user grant list.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public function entitled(User $actor, array $names): array
    {
        if ($actor->company === null) {
            return [];
        }

        $entitlements = Entitlements::for($actor->company);

        return array_values(array_filter($names, fn (string $name) => $entitlements->permits($name)));
    }

    /**
     * @param  list<string>  $permissions
     */
    public function create(User $actor, string $name, array $permissions): Role
    {
        $this->requirePermission($actor, 'roles.create');
        $this->requireCustomRoles($actor);
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

            if (! $role->is_system) {
                $this->requireCustomRoles($actor);
            }

            $this->requireHeld($actor, array_merge($before, $permissions));

            $added = array_values(array_diff($permissions, $before));
            $removed = array_values(array_diff($before, $permissions));

            $role->forceFill([
                'name' => $name,
                'customized_at' => $role->is_system && ($added || $removed) ? now() : $role->customized_at,
            ])->save();
            $role->syncPermissions($permissions);
            $this->resolver->flush();

            AuditLog::record($actor, $actor->company_id, 'role.updated', $role, ['name' => $name, 'added' => $added, 'removed' => $removed]);

            return $role;
        }));
    }

    /**
     * Back to the built-in permissions; CompanyRoleSync follows the defaults again from then on.
     */
    public function reset(User $actor, int $roleId): Role
    {
        $this->requirePermission($actor, 'roles.edit');

        return DB::transaction(fn () => TeamScope::run($actor->company_id, function () use ($actor, $roleId) {
            $role = $this->editableRole($actor, $roleId);
            $defaults = $this->defaults($role) ?? throw new AuthorizationException('Only built-in roles have defaults.');

            $this->requireHeld($actor, array_merge($role->permissions()->pluck('name')->all(), $defaults));

            $role->forceFill(['customized_at' => null])->save();
            $role->syncPermissions($defaults);
            $this->resolver->flush();

            AuditLog::record($actor, $actor->company_id, 'role.reset', $role, ['name' => $role->name]);

            return $role;
        }));
    }

    public function delete(User $actor, int $roleId): void
    {
        $this->requirePermission($actor, 'roles.delete');
        $this->requireCustomRoles($actor);

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

    /**
     * @return Builder<Role>
     */
    private function team(User $actor): Builder
    {
        return Role::query()->where('team_id', $actor->company_id)->where('guard_name', 'web');
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

    private function requireCustomRoles(User $actor): void
    {
        if (! $this->customRolesAllowed($actor)) {
            throw new AuthorizationException('Custom roles are not part of your plan.');
        }
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

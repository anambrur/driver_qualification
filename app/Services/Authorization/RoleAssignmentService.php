<?php

namespace App\Services\Authorization;

use App\Authorization\RoleTemplates;
use App\Authorization\TeamScope;
use App\Authorization\TenantPermissionResolver;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Permission;
use App\Models\PlatformUser;
use App\Models\Role;
use App\Models\User;
use App\Services\Entitlements\Entitlements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Role assignments and direct grants (06 §8). Works in the actor's own team: their company, or
 * team 0 for platform staff, where at least two active Platform Owners must remain.
 */
class RoleAssignmentService
{
    public function __construct(private TenantPermissionResolver $resolver) {}

    public function assign(User|PlatformUser $actor, User|PlatformUser $target, int $roleId): void
    {
        $this->authorizeTarget($actor, $target, $actor instanceof User ? 'users.edit' : 'platform.staff.manage');

        DB::transaction(fn () => TeamScope::run($this->resolver->teamOf($actor), function () use ($actor, $target, $roleId) {
            $role = $this->assignableRole($actor, $roleId);

            $target->assignRole($role);
            $this->changed($actor, $target, 'role.assigned', ['role' => $role->name]);
        }));
    }

    public function remove(User|PlatformUser $actor, User|PlatformUser $target, int $roleId): void
    {
        $this->authorizeTarget($actor, $target, $actor instanceof User ? 'users.edit' : 'platform.staff.manage');

        DB::transaction(fn () => TeamScope::run($this->resolver->teamOf($actor), function () use ($actor, $target, $roleId) {
            $role = $this->assignableRole($actor, $roleId);

            if ($role->system_key === RoleTemplates::PLATFORM_OWNER) {
                $this->keepTwoPlatformOwners($role, $target);
            }

            $target->removeRole($role);
            $this->changed($actor, $target, 'role.removed', ['role' => $role->name]);
        }));
    }

    /**
     * Makes the target's roles match $roleIds through assign() and remove(), so every change is
     * checked and audited. Locked roles the target holds (Owner, Driver) and roles the actor
     * can't assign are left alone.
     *
     * @param  list<int>  $roleIds
     */
    public function syncRoles(User $actor, User $target, array $roleIds): void
    {
        $this->authorizeTarget($actor, $target, 'users.edit');

        DB::transaction(function () use ($actor, $target, $roleIds) {
            $held = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.team_id', $target->company_id)
                ->where('model_has_roles.model_type', $target->getMorphClass())
                ->where('model_has_roles.model_id', $target->id)
                ->pluck('roles.system_key', 'roles.id')
                ->all();

            foreach (array_diff($roleIds, array_keys($held)) as $roleId) {
                $this->assign($actor, $target, $roleId);
            }

            // Roles above the actor (rule 3) are neither offered on the form nor removed.
            $removable = Role::query()
                ->whereKey(array_keys(array_filter($held, fn (?string $systemKey) => $systemKey === null)))
                ->with('permissions:id,name')
                ->get()
                ->filter(fn (Role $role) => $this->resolver->holdsAll($actor, $role->permissions->pluck('name')))
                ->modelKeys();

            foreach (array_diff($removable, $roleIds) as $roleId) {
                $this->remove($actor, $target, $roleId);
            }
        });
    }

    public function grantDirect(User|PlatformUser $actor, User|PlatformUser $target, string $permission): void
    {
        $this->authorizeTarget($actor, $target, $actor instanceof User ? 'permissions.grant' : 'platform.staff.manage');
        $this->requireHeld($actor, $permission);

        if ($actor instanceof User && $actor->company && ! Entitlements::for($actor->company)->permits($permission)) {
            throw new AuthorizationException('This permission is not part of your plan.');
        }

        DB::transaction(fn () => TeamScope::run($this->resolver->teamOf($actor), function () use ($actor, $target, $permission) {
            $target->givePermissionTo($permission);
            $this->changed($actor, $target, 'permission.granted', ['permission' => $permission]);
        }));
    }

    public function revokeDirect(User|PlatformUser $actor, User|PlatformUser $target, string $permission): void
    {
        $this->authorizeTarget($actor, $target, $actor instanceof User ? 'permissions.grant' : 'platform.staff.manage');
        $this->requireHeld($actor, $permission);

        DB::transaction(fn () => TeamScope::run($this->resolver->teamOf($actor), function () use ($actor, $target, $permission) {
            $target->revokePermissionTo($permission);
            $this->changed($actor, $target, 'permission.revoked', ['permission' => $permission]);
        }));
    }

    /**
     * Same team, not yourself, the actor holds the management permission, and only the Owner
     * may touch the Owner (who can't edit themselves, so the Owner role moves only through
     * OwnershipService).
     */
    public function authorizeTarget(User|PlatformUser $actor, User|PlatformUser $target, string $permission): void
    {
        $sameTeam = $actor::class === $target::class
            && $this->resolver->teamOf($actor) === $this->resolver->teamOf($target)
            && ! ($target instanceof User && $target->user_type === 'support');

        if (! $sameTeam) {
            throw (new ModelNotFoundException)->setModel($target::class, [$target->getKey()]);
        }

        if (! $this->resolver->has($actor, $permission)) {
            throw new AuthorizationException;
        }

        if ($actor->is($target)) {
            throw new AuthorizationException('You cannot change your own account here.');
        }

        if ($target instanceof User && (int) Company::query()->whereKey($target->company_id)->value('owner_user_id') === (int) $target->id) {
            throw new AuthorizationException('Only the Owner can change the Owner.');
        }
    }

    /**
     * A role of the actor's team whose permissions the actor holds. Locked company roles are
     * never assigned or removed here; platform roles are all system roles and stay assignable.
     */
    public function assignableRole(User|PlatformUser $actor, int $roleId): Role
    {
        $role = Role::query()
            ->where('team_id', $this->resolver->teamOf($actor))
            ->where('guard_name', $this->resolver->guardOf($actor))
            ->lockForUpdate()
            ->findOrFail($roleId);

        if ($actor instanceof User && $role->isLocked()) {
            throw new AuthorizationException('This role cannot be assigned or removed here.');
        }

        if (! $this->resolver->holdsAll($actor, $role->permissions()->pluck('name')->all())) {
            throw new AuthorizationException('You can only assign roles whose permissions you hold.');
        }

        return $role;
    }

    private function requireHeld(User|PlatformUser $actor, string $permission): void
    {
        if (Permission::fromCatalogue($permission, $this->resolver->guardOf($actor)) === null) {
            throw new AuthorizationException("Unknown permission [{$permission}].");
        }

        if (! $this->resolver->has($actor, $permission)) {
            throw new AuthorizationException('You can only grant permissions you hold.');
        }
    }

    /**
     * 04 §3, for disabling staff: if $target is a Platform Owner, at least two other active ones
     * must remain. Locks the Platform Owner role row like remove() does; call it inside the
     * transaction that changes the target.
     */
    public function assertPlatformOwnersRemainWithout(PlatformUser $target): void
    {
        $role = Role::query()->where('team_id', 0)->where('system_key', RoleTemplates::PLATFORM_OWNER)->lockForUpdate()->firstOrFail();

        $isOwner = DB::table('model_has_roles')
            ->where('team_id', 0)
            ->where('role_id', $role->id)
            ->where('model_type', $target->getMorphClass())
            ->where('model_id', $target->getKey())
            ->exists();

        if ($isOwner) {
            $this->keepTwoPlatformOwners($role, $target);
        }
    }

    /**
     * 04 §3: removing a Platform Owner must leave at least two active ones. The role row is
     * locked by assignableRole(), so concurrent removals queue behind each other.
     */
    private function keepTwoPlatformOwners(Role $role, User|PlatformUser $target): void
    {
        $remaining = PlatformUser::query()
            ->where('status', 'active')
            ->whereKeyNot($target->getKey())
            ->whereIn('id', DB::table('model_has_roles')
                ->where('team_id', 0)
                ->where('role_id', $role->id)
                ->where('model_type', (new PlatformUser)->getMorphClass())
                ->select('model_id'))
            ->count();

        if ($remaining < 2) {
            throw new AuthorizationException('At least two active Platform Owners must remain.');
        }
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function changed(User|PlatformUser $actor, User|PlatformUser $target, string $action, array $changes): void
    {
        $this->resolver->forget($target);

        AuditLog::record($actor, $actor instanceof User ? $actor->company_id : null, $action, $target, $changes);
    }
}

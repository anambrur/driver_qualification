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
        $this->guard($actor, $target, $actor instanceof User ? 'users.edit' : 'platform.staff.manage');

        DB::transaction(fn () => TeamScope::run($this->resolver->teamOf($actor), function () use ($actor, $target, $roleId) {
            $role = $this->assignableRole($actor, $roleId);

            $target->assignRole($role);
            $this->changed($actor, $target, 'role.assigned', ['role' => $role->name]);
        }));
    }

    public function remove(User|PlatformUser $actor, User|PlatformUser $target, int $roleId): void
    {
        $this->guard($actor, $target, $actor instanceof User ? 'users.edit' : 'platform.staff.manage');

        DB::transaction(fn () => TeamScope::run($this->resolver->teamOf($actor), function () use ($actor, $target, $roleId) {
            $role = $this->assignableRole($actor, $roleId);

            if ($role->system_key === RoleTemplates::PLATFORM_OWNER) {
                $this->keepTwoPlatformOwners($role, $target);
            }

            $target->removeRole($role);
            $this->changed($actor, $target, 'role.removed', ['role' => $role->name]);
        }));
    }

    public function grantDirect(User|PlatformUser $actor, User|PlatformUser $target, string $permission): void
    {
        $this->guard($actor, $target, $actor instanceof User ? 'permissions.grant' : 'platform.staff.manage');
        $this->requireHeld($actor, $permission);

        DB::transaction(fn () => TeamScope::run($this->resolver->teamOf($actor), function () use ($actor, $target, $permission) {
            $target->givePermissionTo($permission);
            $this->changed($actor, $target, 'permission.granted', ['permission' => $permission]);
        }));
    }

    public function revokeDirect(User|PlatformUser $actor, User|PlatformUser $target, string $permission): void
    {
        $this->guard($actor, $target, $actor instanceof User ? 'permissions.grant' : 'platform.staff.manage');
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
    private function guard(User|PlatformUser $actor, User|PlatformUser $target, string $permission): void
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
            throw new AuthorizationException('You cannot change your own roles or permissions.');
        }

        if ($target instanceof User && (int) Company::query()->whereKey($target->company_id)->value('owner_user_id') === (int) $target->id) {
            throw new AuthorizationException('Only the Owner can change the Owner.');
        }
    }

    /**
     * A role of the actor's team whose permissions the actor holds. Locked company roles are
     * never assigned or removed here; platform roles are all system roles and stay assignable.
     */
    private function assignableRole(User|PlatformUser $actor, int $roleId): Role
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

<?php

namespace App\Authorization;

use App\Models\PlatformUser;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Contracts\Permission as PermissionContract;

/**
 * Database-first permission check (06 §2, §4.2): one indexed query per user and request returns
 * the user's effective permissions in their own team. The team is always the user's company
 * (or 0 for platform staff), never request input. Bound as a scoped instance, so the memo lives
 * for one request or job only; there is no cross-request cache.
 */
class TenantPermissionResolver
{
    /** @var array<string, array<string, true>> */
    private array $memo = [];

    /**
     * Backs hasPermissionTo() on User and PlatformUser. Unknown permissions throw
     * PermissionDoesNotExist, as in stock Spatie.
     */
    public function allows(User|PlatformUser $user, string|int|PermissionContract|BackedEnum $permission, ?string $guardName = null): bool
    {
        $permission = $user->filterPermission($permission, $guardName);

        if ($permission->guard_name !== $this->guardOf($user)) {
            return false;
        }

        return $this->has($user, $permission->name);
    }

    public function has(User|PlatformUser $user, string $permission): bool
    {
        return isset($this->names($user)[$permission]);
    }

    /**
     * @param  iterable<string>  $permissions
     */
    public function holdsAll(User|PlatformUser $user, iterable $permissions): bool
    {
        $held = $this->names($user);

        foreach ($permissions as $permission) {
            if (! isset($held[$permission])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, true>
     */
    public function names(User|PlatformUser $user): array
    {
        $key = $this->key($user);

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $guard = $this->guardOf($user);
        $type = $user->getMorphClass();
        $id = $user->getKey();
        $team = $this->teamOf($user);

        $rows = DB::select(
            'SELECT p.name FROM model_has_roles mhr
               JOIN roles r ON r.id = mhr.role_id AND r.team_id = mhr.team_id
               JOIN role_has_permissions rhp ON rhp.role_id = r.id
               JOIN permissions p ON p.id = rhp.permission_id AND p.guard_name = ?
              WHERE mhr.model_type = ? AND mhr.model_id = ? AND mhr.team_id = ?
             UNION
             SELECT p.name FROM model_has_permissions mhp
               JOIN permissions p ON p.id = mhp.permission_id AND p.guard_name = ?
              WHERE mhp.model_type = ? AND mhp.model_id = ? AND mhp.team_id = ?',
            [$guard, $type, $id, $team, $guard, $type, $id, $team]
        );

        return $this->memo[$key] = array_fill_keys(array_column($rows, 'name'), true);
    }

    public function forget(User|PlatformUser $user): void
    {
        unset($this->memo[$this->key($user)]);
    }

    public function flush(): void
    {
        $this->memo = [];
    }

    public function teamOf(User|PlatformUser $user): int
    {
        return $user instanceof PlatformUser ? 0 : (int) $user->company_id;
    }

    public function guardOf(User|PlatformUser $user): string
    {
        return $user instanceof PlatformUser ? 'platform' : 'web';
    }

    private function key(User|PlatformUser $user): string
    {
        return $this->guardOf($user).':'.$user->getKey().':'.$this->teamOf($user);
    }
}

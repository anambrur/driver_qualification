<?php

namespace App\Services\Platform;

use App\Authorization\TenantPermissionResolver;
use App\Models\AuditLog;
use App\Models\PlatformUser;
use App\Models\Role;
use App\Services\Authorization\RoleAssignmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Platform staff (04 §3): invite, edit, disable. Role changes go through RoleAssignmentService,
 * so the two-Owner minimum and the escalation rules apply; nobody edits their own account.
 */
class PlatformStaffService
{
    public function __construct(
        private EmailIdentityService $emails,
        private RoleAssignmentService $assignments,
        private TenantPermissionResolver $resolver,
    ) {}

    /**
     * The new staff member sets their own password through the emailed reset link.
     *
     * @param  list<int>  $roleIds
     */
    public function invite(PlatformUser $actor, string $name, string $email, array $roleIds): PlatformUser
    {
        if (! $this->resolver->has($actor, 'platform.staff.manage')) {
            throw new AuthorizationException;
        }

        $staff = $this->emails->claim($email, function (string $email) use ($actor, $name, $roleIds) {
            $staff = PlatformUser::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Str::random(64),
                'status' => 'active',
            ]);

            foreach ($roleIds as $roleId) {
                $this->assignments->assign($actor, $staff, $roleId);
            }

            AuditLog::record($actor, null, 'platform_user.invited', $staff, ['email' => $email]);

            return $staff;
        });

        Password::broker('platform_users')->sendResetLink(['email' => $staff->email]);

        return $staff;
    }

    /**
     * @param  list<int>  $roleIds
     */
    public function update(PlatformUser $actor, PlatformUser $staff, string $name, string $email, array $roleIds): void
    {
        $this->assignments->authorizeTarget($actor, $staff, 'platform.staff.manage');

        $this->emails->claim($email, function (string $email) use ($actor, $staff, $name, $roleIds) {
            $staff->fill(['name' => $name, 'email' => $email]);
            $changes = $staff->getDirty();
            $staff->save();

            if ($changes !== []) {
                AuditLog::record($actor, null, 'platform_user.updated', $staff, $changes);
            }

            $held = $this->heldRoleIds($staff);

            foreach (array_diff($roleIds, $held) as $roleId) {
                $this->assignments->assign($actor, $staff, $roleId);
            }

            foreach (array_diff($held, $roleIds) as $roleId) {
                $this->assignments->remove($actor, $staff, $roleId);
            }
        }, $staff);
    }

    /**
     * A disabled staff member is logged out on their next request (EnsurePlatformSession).
     */
    public function setStatus(PlatformUser $actor, PlatformUser $staff, string $status): void
    {
        $this->assignments->authorizeTarget($actor, $staff, 'platform.staff.manage');

        if ($staff->status === $status) {
            return;
        }

        DB::transaction(function () use ($actor, $staff, $status) {
            if ($status !== 'active') {
                $this->assignments->assertPlatformOwnersRemainWithout($staff);
            }

            $staff->forceFill(['status' => $status])->save();
            AuditLog::record($actor, null, 'platform_user.status_changed', $staff, ['status' => $status]);
        });

        if ($status !== 'active') {
            app(SupportSessionService::class)->endActiveFor($staff, 'staff disabled');
        }
    }

    /**
     * @return list<int>
     */
    public function heldRoleIds(PlatformUser $staff): array
    {
        return DB::table('model_has_roles')
            ->where('team_id', 0)
            ->where('model_type', $staff->getMorphClass())
            ->where('model_id', $staff->id)
            ->pluck('role_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The platform roles in team 0 (04 §3), for the staff forms.
     *
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return Role::query()->where('team_id', 0)->where('guard_name', 'platform')->orderBy('id')->get();
    }
}

<?php

namespace App\Services\Platform;

use App\Authorization\RoleTemplates;
use App\Authorization\TeamScope;
use App\Authorization\TenantPermissionResolver;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PlatformSupportSession;
use App\Models\PlatformUser;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\SupportSessionNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Support sessions (Q1, 04 §4): requested by platform staff, approved by the company's Owner and
 * by a second Platform Owner (four-eyes), then started by the requester as the company's support
 * user with a locked Support role. There is no path that skips either approval.
 */
class SupportSessionService
{
    /** The PHP session key that ties the web-guard support login to its support session. */
    public const SESSION_KEY = 'support_session_id';

    public function __construct(private TenantPermissionResolver $resolver) {}

    public function request(PlatformUser $staff, Company $company, string $scope, string $reason): PlatformSupportSession
    {
        $this->requirePermission($staff, 'platform.support.request');

        if (! in_array($scope, PlatformSupportSession::SCOPES, true)) {
            throw ValidationException::withMessages(['scope' => 'Choose read or read and write.']);
        }

        if ($company->status !== 'active') {
            throw ValidationException::withMessages(['company_id' => 'The company is not active.']);
        }

        $session = DB::transaction(function () use ($staff, $company, $scope, $reason) {
            $session = PlatformSupportSession::query()->create([
                'company_id' => $company->id,
                'platform_user_id' => $staff->id,
                'reason' => $reason,
                'scope' => $scope,
                'status' => 'requested',
                'requested_at' => now(),
            ]);

            $this->audit($staff, $session, 'support_session.requested', ['scope' => $scope, 'reason' => $reason]);

            return $session;
        });

        $this->notifyOwner($session, 'requested');

        return $session;
    }

    /**
     * Only the company's current Owner approves for the tenant. Another company's session is a 404.
     */
    public function approveAsTenant(User $owner, PlatformSupportSession $session): void
    {
        $this->requireOwner($owner, $session);

        DB::transaction(function () use ($owner, $session) {
            $locked = $this->lockOpen($session);

            if ($locked->tenant_approved_by_user_id !== null) {
                return;
            }

            $locked->forceFill(['tenant_approved_by_user_id' => $owner->id, 'tenant_approved_at' => now()]);
            $this->markApprovedWhenBoth($locked);
            $this->audit($owner, $locked, 'support_session.tenant_approved');
        });
    }

    /**
     * The second, four-eyes approval: a Platform Owner who did not ask, while at least two active
     * Platform Owners exist (04 §3).
     */
    public function approveAsPlatform(PlatformUser $approver, PlatformSupportSession $session): void
    {
        $this->requirePermission($approver, 'platform.support.approve');

        if ((int) $approver->id === (int) $session->platform_user_id) {
            throw new AuthorizationException('A support session needs a second Platform Owner, not the requester.');
        }

        if ($this->activePlatformOwners() < 2) {
            throw new AuthorizationException('Support sessions need at least two active Platform Owners.');
        }

        DB::transaction(function () use ($approver, $session) {
            $locked = $this->lockOpen($session);

            if ($locked->platform_approved_by_platform_user_id !== null) {
                return;
            }

            $locked->forceFill(['platform_approved_by_platform_user_id' => $approver->id, 'platform_approved_at' => now()]);
            $this->markApprovedWhenBoth($locked);
            $this->audit($approver, $locked, 'support_session.platform_approved');
        });
    }

    public function reject(User|PlatformUser $actor, PlatformSupportSession $session, string $reason): void
    {
        $actor instanceof User
            ? $this->requireOwner($actor, $session)
            : $this->requirePermission($actor, 'platform.support.approve');

        DB::transaction(function () use ($actor, $session, $reason) {
            $locked = $this->lockOpen($session);
            $locked->forceFill([
                'status' => 'rejected',
                'rejected_by_type' => $actor instanceof User ? 'user' : 'platform_user',
                'rejected_by_id' => $actor->getKey(),
                'rejection_reason' => $reason,
            ])->save();

            $this->audit($actor, $locked, 'support_session.rejected', ['reason' => $reason]);
        });
    }

    /**
     * Activates the company's support user with the session's locked Support role. The caller logs
     * the requester into the web guard as the returned user.
     */
    public function start(PlatformUser $staff, PlatformSupportSession $session): User
    {
        if ((int) $staff->id !== (int) $session->platform_user_id) {
            throw new AuthorizationException('Only the staff member who asked can start this session.');
        }

        $support = DB::transaction(function () use ($staff, $session) {
            $company = Company::query()->whereKey($session->company_id)->lockForUpdate()->firstOrFail();
            $locked = PlatformSupportSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($company->status !== 'active') {
                throw ValidationException::withMessages(['session' => 'The company is not active.']);
            }

            if ($locked->status !== 'approved' || ! $locked->isOpen()) {
                throw ValidationException::withMessages(['session' => 'This session is not approved, or its approval has expired.']);
            }

            if (PlatformSupportSession::query()->active()->where('company_id', $locked->company_id)->exists()) {
                throw ValidationException::withMessages(['session' => 'Another support session is active for this company.']);
            }

            $support = $this->supportUser($locked->company_id);
            $this->setSupportRole($support, $locked->supportRoleKey());
            $support->forceFill(['status' => 'active'])->save();

            $minutes = (int) SiteSetting::getSettings()->support_session_max_minutes ?: 240;

            try {
                $locked->forceFill(['status' => 'active', 'started_at' => now(), 'expires_at' => now()->addMinutes($minutes)])->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['session' => 'Another support session is active for this company.']);
            }

            $this->audit($staff, $locked, 'support_session.started', ['expires_at' => $locked->expires_at->toIso8601String()]);
            $session->setRawAttributes($locked->getAttributes(), true);

            return $support;
        });

        $this->notifyOwner($session, 'started');

        return $support;
    }

    /**
     * Ends an active session: by its staff member, the Owner's revoke, expiry, or the staff
     * member's logout or disabling. The support user goes back to inactive with no role.
     */
    public function end(PlatformSupportSession $session, string $reason, User|PlatformUser|null $by = null): void
    {
        $ended = DB::transaction(function () use ($session, $reason, $by) {
            $locked = PlatformSupportSession::query()->lockForUpdate()->find($session->id);

            if ($locked?->status !== 'active') {
                return false;
            }

            $locked->forceFill([
                'status' => 'ended',
                'ended_at' => now(),
                'end_reason' => $reason,
                'revoked_by_user_id' => $by instanceof User ? $by->id : null,
            ])->save();

            $support = $this->supportUser($locked->company_id);
            $this->setSupportRole($support, null);
            $support->forceFill(['status' => 'inactive'])->save();

            $by === null
                ? AuditLog::write('system', null, $locked->company_id, 'support_session.ended', $locked, ['reason' => $reason], supportSessionId: $locked->id)
                : $this->audit($by, $locked, 'support_session.ended', ['reason' => $reason]);

            return true;
        });

        if ($ended) {
            $this->notifyOwner($session, 'ended');
        }
    }

    /**
     * Owner-side revoke: ends an active session, or rejects one that hasn't started.
     */
    public function revoke(User $owner, PlatformSupportSession $session): void
    {
        $this->requireOwner($owner, $session);

        $session->status === 'active'
            ? $this->end($session, 'revoked by owner', $owner)
            : $this->reject($owner, $session, 'Revoked by the company owner.');
    }

    /**
     * Scheduled (and lazily from ResolveTenant): stale requests expire, overdue sessions end.
     */
    public function expireStale(): int
    {
        $stale = PlatformSupportSession::query()
            ->whereIn('status', ['requested', 'approved'])
            ->where('requested_at', '<=', now()->subHours(PlatformSupportSession::REQUEST_WINDOW_HOURS))
            ->get();

        $stale->each(function (PlatformSupportSession $session) {
            $session->forceFill(['status' => 'expired'])->save();
            AuditLog::write('system', null, $session->company_id, 'support_session.expired', $session, supportSessionId: $session->id);
        });
        $expired = $stale->count();

        $overdue = PlatformSupportSession::query()->active()->where('expires_at', '<=', now())->get();
        $overdue->each(fn (PlatformSupportSession $session) => $this->end($session, 'expired'));

        return $expired + $overdue->count();
    }

    /**
     * The active session of a staff member, if any (for logout and disabling).
     */
    public function endActiveFor(PlatformUser $staff, string $reason): void
    {
        PlatformSupportSession::query()->active()->where('platform_user_id', $staff->id)->get()
            ->each(fn (PlatformSupportSession $session) => $this->end($session, $reason));
    }

    public function supportUser(int $companyId): User
    {
        return User::query()->where('company_id', $companyId)->where('user_type', 'support')->firstOrFail();
    }

    private function markApprovedWhenBoth(PlatformSupportSession $session): void
    {
        if ($session->tenant_approved_by_user_id !== null && $session->platform_approved_by_platform_user_id !== null) {
            $session->status = 'approved';
        }

        $session->save();
    }

    private function lockOpen(PlatformSupportSession $session): PlatformSupportSession
    {
        $locked = PlatformSupportSession::query()->lockForUpdate()->findOrFail($session->id);

        if (! $locked->isOpen()) {
            throw ValidationException::withMessages(['session' => 'This support session can no longer be approved or rejected.']);
        }

        return $locked;
    }

    /**
     * Gives the support user exactly one locked Support role in its company's team, or none.
     */
    private function setSupportRole(User $support, ?string $systemKey): void
    {
        TeamScope::run((int) $support->company_id, function () use ($support, $systemKey) {
            // The support user never holds any other role (06 §8: locked roles are system-assigned)
            $support->syncRoles($systemKey === null ? [] : [
                Role::query()->where('team_id', $support->company_id)->where('system_key', $systemKey)->firstOrFail(),
            ]);
        });

        $this->resolver->forget($support);
    }

    private function requireOwner(User $user, PlatformSupportSession $session): void
    {
        if ((int) $user->company_id !== (int) $session->company_id) {
            throw (new ModelNotFoundException)->setModel(PlatformSupportSession::class, [$session->id]);
        }

        if ((int) Company::query()->whereKey($session->company_id)->value('owner_user_id') !== (int) $user->id) {
            throw new AuthorizationException('Only the company owner can approve support access.');
        }
    }

    private function requirePermission(PlatformUser $staff, string $permission): void
    {
        if (! $this->resolver->has($staff, $permission)) {
            throw new AuthorizationException;
        }
    }

    private function activePlatformOwners(): int
    {
        return PlatformUser::query()->where('status', 'active')
            ->whereIn('id', DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.team_id', 0)
                ->where('roles.system_key', RoleTemplates::PLATFORM_OWNER)
                ->where('model_has_roles.model_type', (new PlatformUser)->getMorphClass())
                ->select('model_has_roles.model_id'))
            ->count();
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function audit(User|PlatformUser $actor, PlatformSupportSession $session, string $action, array $changes = []): void
    {
        AuditLog::write(
            $actor instanceof PlatformUser ? 'platform_user' : 'user',
            $actor->getKey(),
            $session->company_id,
            $action,
            $session,
            $changes,
            supportSessionId: $session->id,
        );
    }

    private function notifyOwner(PlatformSupportSession $session, string $event): void
    {
        Company::query()->find($session->company_id)?->owner?->notify(new SupportSessionNotification($session->id, $event));
    }
}

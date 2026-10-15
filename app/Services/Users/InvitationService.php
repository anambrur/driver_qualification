<?php

namespace App\Services\Users;

use App\Authorization\TeamScope;
use App\Authorization\TenantPermissionResolver;
use App\Features\Feature;
use App\Mail\CompanyInvitationMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentService;
use App\Services\Entitlements\Limits;
use App\Services\Platform\EmailIdentityService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Company invitations (10 §5). The inviter only ever learns "invitation sent", so accounts in
 * other companies are never revealed (D3). The role must be assignable by the inviter (06 §8).
 */
class InvitationService
{
    public const EXPIRES_IN_DAYS = 7;

    public const EMAIL_TAKEN = 'This email already has an account, use another email.';

    public function __construct(
        private RoleAssignmentService $assignments,
        private TenantPermissionResolver $resolver,
        private EmailIdentityService $emails,
    ) {}

    /**
     * @return Collection<int, CompanyInvitation>
     */
    public function pending(User $actor): Collection
    {
        return CompanyInvitation::query()
            ->where('company_id', $actor->company_id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->with('role:id,name')
            ->latest()
            ->get();
    }

    public function invite(User $actor, string $email, int $roleId): CompanyInvitation
    {
        $this->requirePermission($actor);
        $email = mb_strtolower(trim($email));
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($actor, $email, $roleId, $token) {
            $role = $this->assignments->assignableRole($actor, $roleId);

            CompanyInvitation::query()
                ->where('company_id', $actor->company_id)
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $invitation = CompanyInvitation::create([
                'company_id' => $actor->company_id,
                'email' => $email,
                'role_id' => $role->id,
                'token_hash' => CompanyInvitation::hashToken($token),
                'invited_by_user_id' => $actor->id,
                'expires_at' => now()->addDays(self::EXPIRES_IN_DAYS),
            ]);

            AuditLog::record($actor, $actor->company_id, 'user.invited', $invitation, ['email' => $email, 'role' => $role->name]);

            return $invitation;
        });

        Mail::to($email)->queue(new CompanyInvitationMail($invitation, $token));

        return $invitation;
    }

    /**
     * A new token and expiry; the old link stops working.
     */
    public function resend(User $actor, int $invitationId): void
    {
        $this->requirePermission($actor);
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($actor, $invitationId, $token) {
            $invitation = $this->open($actor, $invitationId);
            $this->assignments->assignableRole($actor, $invitation->role_id);

            $invitation->forceFill([
                'token_hash' => CompanyInvitation::hashToken($token),
                'expires_at' => now()->addDays(self::EXPIRES_IN_DAYS),
            ])->save();

            AuditLog::record($actor, $actor->company_id, 'user.invitation_resent', $invitation, ['email' => $invitation->email]);

            return $invitation;
        });

        Mail::to($invitation->email)->queue(new CompanyInvitationMail($invitation, $token));
    }

    public function revoke(User $actor, int $invitationId): void
    {
        $this->requirePermission($actor);

        DB::transaction(function () use ($actor, $invitationId) {
            $invitation = $this->open($actor, $invitationId);
            $invitation->forceFill(['revoked_at' => now()])->save();

            AuditLog::record($actor, $actor->company_id, 'user.invitation_revoked', $invitation, ['email' => $invitation->email]);
        });
    }

    /**
     * The pending invitation for a token from an email link, or null. Runs without a tenant
     * context (the invitee is a guest), so it looks up by token hash only.
     */
    public function findByToken(string $token, bool $lock = false): ?CompanyInvitation
    {
        $query = CompanyInvitation::withoutTenancy()
            ->where('token_hash', CompanyInvitation::hashToken($token))
            ->pending();

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function accept(string $token, string $name, string $password): User
    {
        $email = $this->findByToken($token)->email
            ?? throw (new ModelNotFoundException)->setModel(CompanyInvitation::class);

        try {
            // Never also a platform login (D3a); the email lock spans the check and the insert.
            return $this->emails->claim($email, fn () => $this->createInvitedUser($token, $name, $password));
        } catch (ValidationException $e) {
            // The invitation flow keeps its own wording; a lock timeout keeps "try again"
            throw ($e->errors()['email'][0] ?? null) === EmailIdentityService::TAKEN
                ? ValidationException::withMessages(['email' => self::EMAIL_TAKEN])
                : $e;
        }
    }

    private function createInvitedUser(string $token, string $name, string $password): User
    {
        $invitation = $this->findByToken($token, lock: true)
            ?? throw (new ModelNotFoundException)->setModel(CompanyInvitation::class);

        $company = Company::query()->lockForUpdate()->findOrFail($invitation->company_id);

        if ($company->status !== 'active') {
            throw new AuthorizationException('This company account is not active.');
        }

        Limits::assertCanCreate($company, Feature::MaxUsers);

        $user = new User([
            'company_id' => $company->id,
            'user_type' => 'member',
            'name' => $name,
            'email' => $invitation->email,
            'password' => $password,
            'status' => 'active',
        ]);
        // The emailed token proves the address.
        $user->forceFill(['email_verified_at' => now()]);

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => self::EMAIL_TAKEN]);
        }

        $role = Role::query()->where('team_id', $company->id)->findOrFail($invitation->role_id);
        TeamScope::run($company->id, fn () => $user->assignRole($role));

        $invitation->forceFill(['accepted_at' => now(), 'accepted_user_id' => $user->id])->save();

        AuditLog::record($user, $company->id, 'user.invited_accepted', $user, ['invitation_id' => $invitation->id, 'role' => $role->name]);

        return $user;
    }

    private function open(User $actor, int $invitationId): CompanyInvitation
    {
        return CompanyInvitation::query()
            ->where('company_id', $actor->company_id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->lockForUpdate()
            ->findOrFail($invitationId);
    }

    private function requirePermission(User $actor): void
    {
        if (! $this->resolver->has($actor, 'users.create')) {
            throw new AuthorizationException;
        }
    }
}

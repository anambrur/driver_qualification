<?php

use App\Mail\CompanyInvitationMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\Role;
use App\Models\User;
use App\Services\Users\InvitationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Actors;

/*
| 10 §5: invitations. The inviter only sees "invitation sent" (D3), the role must be assignable
| by the inviter (06 §8), only the token hash is stored, and accepting creates a verified user
| in the company with the role in the company's team.
*/

function invitationRoleId(Company $company, string $name): int
{
    return (int) Role::query()->where('team_id', $company->id)->where('name', $name)->value('id');
}

/**
 * Sends an invitation over HTTP as $actor and returns the plaintext token from the queued mail.
 */
function sendInvitation($test, User $actor, string $email, int $roleId): string
{
    Mail::fake();

    $test->actingAs($actor)
        ->post(route('users.invitations.store'), ['email' => $email, 'role_id' => $roleId])
        ->assertRedirect(route('users.index'));

    $token = null;
    Mail::assertQueued(CompanyInvitationMail::class, function (CompanyInvitationMail $mail) use ($email, &$token) {
        $token = $mail->token;

        return $mail->hasTo(mb_strtolower($email));
    });
    auth()->logout();

    return $token;
}

function acceptPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Person',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ], $overrides);
}

function teamRolesOf(User $user): array
{
    return DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $user->id)
        ->where('model_has_roles.team_id', $user->company_id)
        ->orderBy('roles.name')
        ->pluck('roles.name')
        ->all();
}

describe('inviting', function () {
    it('stores only the token hash and queues the invitation email', function () {
        $company = Actors::company();

        $token = sendInvitation($this, $company->owner, 'New@Example.com', invitationRoleId($company, 'Dispatcher'));

        $invitation = CompanyInvitation::withoutTenancy()->sole();
        expect($invitation->email)->toBe('new@example.com')
            ->and($invitation->company_id)->toBe($company->id)
            ->and($invitation->token_hash)->toBe(hash('sha256', $token))
            ->and(DB::table('company_invitations')->where('token_hash', $token)->exists())->toBeFalse()
            ->and($invitation->expires_at->isSameDay(now()->addDays(InvitationService::EXPIRES_IN_DAYS)))->toBeTrue()
            ->and(AuditLog::where('action', 'user.invited')->where('company_id', $company->id)->exists())->toBeTrue();
    });

    it('answers "invitation sent" even when the email already has an account elsewhere', function () {
        $company = Actors::company();
        $existing = Actors::dispatcher();
        Mail::fake();

        $this->actingAs($company->owner)
            ->post(route('users.invitations.store'), ['email' => $existing->email, 'role_id' => invitationRoleId($company, 'Dispatcher')])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        Mail::assertQueued(CompanyInvitationMail::class);
    });

    it('replaces a pending invitation for the same email', function () {
        $company = Actors::company();
        $first = sendInvitation($this, $company->owner, 'new@example.com', invitationRoleId($company, 'Dispatcher'));
        sendInvitation($this, $company->owner, 'new@example.com', invitationRoleId($company, 'Office / Compliance'));

        $this->get(route('invitations.show', $first))->assertNotFound();
        expect(CompanyInvitation::withoutTenancy()->whereNull('revoked_at')->count())->toBe(1);
    });

    it('refuses a role with permissions the inviter does not hold', function () {
        $company = Actors::company();
        $inviter = Actors::memberWithPermissions($company, ['users.view', 'users.create']);
        Mail::fake();

        $this->actingAs($inviter)
            ->post(route('users.invitations.store'), ['email' => 'new@example.com', 'role_id' => invitationRoleId($company, 'Dispatcher')])
            ->assertForbidden();

        Mail::assertNothingQueued();
        expect(CompanyInvitation::withoutTenancy()->count())->toBe(0);
    });

    it('refuses a locked role', function (string $role) {
        $company = Actors::company();
        Mail::fake();

        $this->actingAs($company->owner)
            ->post(route('users.invitations.store'), ['email' => 'new@example.com', 'role_id' => invitationRoleId($company, $role)])
            ->assertForbidden();

        Mail::assertNothingQueued();
    })->with(['Owner', 'Driver']);

    it("returns 404 for another company's role", function () {
        Mail::fake();

        $this->actingAs(Actors::companyOwner())
            ->post(route('users.invitations.store'), ['email' => 'new@example.com', 'role_id' => invitationRoleId(Actors::company(), 'Dispatcher')])
            ->assertNotFound();

        Mail::assertNothingQueued();
    });

    it('validates the email', function () {
        $company = Actors::company();

        $this->actingAs($company->owner)
            ->post(route('users.invitations.store'), ['email' => 'not-an-email', 'role_id' => invitationRoleId($company, 'Dispatcher')])
            ->assertSessionHasErrors('email');
    });

    it('throttles sending invitations', function () {
        $company = Actors::company();
        $roleId = invitationRoleId($company, 'Dispatcher');
        Mail::fake();

        foreach (range(1, 20) as $i) {
            $this->actingAs($company->owner)->post(route('users.invitations.store'), ['email' => "p{$i}@example.com", 'role_id' => $roleId]);
        }

        $this->actingAs($company->owner)
            ->post(route('users.invitations.store'), ['email' => 'p21@example.com', 'role_id' => $roleId])
            ->assertStatus(429);
    });

    it('lists pending invitations on the users screen', function () {
        $company = Actors::company();
        sendInvitation($this, $company->owner, 'pending@example.com', invitationRoleId($company, 'Dispatcher'));

        $this->actingAs($company->owner)->get(route('users.index'))->assertOk()->assertSee('pending@example.com');
        $this->actingAs(Actors::companyOwner())->get(route('users.index'))->assertOk()->assertDontSee('pending@example.com');
    });
});

describe('resend and revoke', function () {
    it('rotates the token on resend', function () {
        $company = Actors::company();
        $old = sendInvitation($this, $company->owner, 'new@example.com', invitationRoleId($company, 'Dispatcher'));
        $invitation = CompanyInvitation::withoutTenancy()->sole();
        Mail::fake();

        $this->actingAs($company->owner)->post(route('users.invitations.resend', $invitation->id))->assertRedirect(route('users.index'));

        $new = null;
        Mail::assertQueued(CompanyInvitationMail::class, function (CompanyInvitationMail $mail) use (&$new) {
            $new = $mail->token;

            return true;
        });
        auth()->logout();

        $this->get(route('invitations.show', $old))->assertNotFound();
        $this->get(route('invitations.show', $new))->assertOk();
    });

    it('makes a revoked invitation unusable', function () {
        $company = Actors::company();
        $token = sendInvitation($this, $company->owner, 'new@example.com', invitationRoleId($company, 'Dispatcher'));

        $this->actingAs($company->owner)
            ->delete(route('users.invitations.destroy', CompanyInvitation::withoutTenancy()->sole()->id))
            ->assertRedirect(route('users.index'));
        auth()->logout();

        $this->post(route('invitations.accept', $token), acceptPayload())->assertNotFound();
        expect(User::where('email', 'new@example.com')->exists())->toBeFalse();
    });
});

describe('accepting', function () {
    it('creates a verified user in the company with the role in its team, and logs them in', function () {
        $company = Actors::company();
        $token = sendInvitation($this, $company->owner, 'new@example.com', invitationRoleId($company, 'Dispatcher'));

        $this->get(route('invitations.show', $token))->assertOk()->assertSee($company->company_name);
        $this->post(route('invitations.accept', $token), acceptPayload())->assertRedirect(route('dashboard'));

        $user = User::where('email', 'new@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        expect($user->company_id)->toBe($company->id)
            ->and($user->status)->toBe('active')
            ->and($user->email_verified_at)->not->toBeNull()
            ->and(teamRolesOf($user))->toBe(['Dispatcher'])
            ->and(CompanyInvitation::withoutTenancy()->sole()->accepted_user_id)->toBe($user->id)
            ->and(AuditLog::where('action', 'user.invited_accepted')->where('subject_id', $user->id)->exists())->toBeTrue();
    });

    it('refuses an unknown, expired or already accepted token', function () {
        $company = Actors::company();
        $token = sendInvitation($this, $company->owner, 'new@example.com', invitationRoleId($company, 'Dispatcher'));

        $this->get(route('invitations.show', 'unknown-token'))->assertNotFound();

        $this->travel(InvitationService::EXPIRES_IN_DAYS + 1)->days();
        $this->post(route('invitations.accept', $token), acceptPayload())->assertNotFound();
        $this->travelBack();

        $this->post(route('invitations.accept', $token), acceptPayload())->assertRedirect(route('dashboard'));
        auth()->logout();
        $this->post(route('invitations.accept', $token), acceptPayload(['name' => 'Second']))->assertNotFound();

        expect(User::where('email', 'new@example.com')->count())->toBe(1);
    });

    it('tells the invitee when the email already has an account', function () {
        $company = Actors::company();
        $existing = Actors::dispatcher();
        $token = sendInvitation($this, $company->owner, $existing->email, invitationRoleId($company, 'Dispatcher'));

        $this->post(route('invitations.accept', $token), acceptPayload())
            ->assertSessionHasErrors(['email' => InvitationService::EMAIL_TAKEN]);

        $this->assertGuest();
        expect(User::where('email', $existing->email)->count())->toBe(1)
            ->and(CompanyInvitation::withoutTenancy()->sole()->accepted_at)->toBeNull();
    });

    it('refuses to accept into a suspended company', function () {
        $company = Actors::company();
        $token = sendInvitation($this, $company->owner, 'new@example.com', invitationRoleId($company, 'Dispatcher'));
        Actors::suspended($company);

        $this->post(route('invitations.accept', $token), acceptPayload())->assertForbidden();

        expect(User::where('email', 'new@example.com')->exists())->toBeFalse();
    });

    it('validates the password', function () {
        $company = Actors::company();
        $token = sendInvitation($this, $company->owner, 'new@example.com', invitationRoleId($company, 'Dispatcher'));

        $this->post(route('invitations.accept', $token), acceptPayload(['password_confirmation' => 'different']))
            ->assertSessionHasErrors('password');
    });

    it('throttles accept attempts', function () {
        foreach (range(1, 6) as $attempt) {
            $this->post(route('invitations.accept', 'guess-'.$attempt), acceptPayload())->assertNotFound();
        }

        $this->post(route('invitations.accept', 'guess-7'), acceptPayload())->assertStatus(429);
    });
});

it('acceptance: an Owner invites a user, creates a role and assigns it', function () {
    $company = Actors::company();
    $owner = $company->owner;

    $token = sendInvitation($this, $owner, 'yard@example.com', invitationRoleId($company, 'Dispatcher'));
    $this->post(route('invitations.accept', $token), acceptPayload())->assertRedirect(route('dashboard'));
    auth()->logout();
    $member = User::where('email', 'yard@example.com')->sole();

    $this->actingAs($owner)
        ->post(route('admin.roles.store'), ['name' => 'Yard', 'permissions' => ['vehicles.view', 'trailers.view']])
        ->assertRedirect(route('admin.roles.index'));

    $this->actingAs($owner)
        ->put(route('users.update', $member->id), ['name' => $member->name, 'roles' => [invitationRoleId($company, 'Yard')]])
        ->assertRedirect(route('users.index'));

    Actors::actingAsTenant($member->fresh());
    expect(teamRolesOf($member))->toBe(['Yard'])
        ->and($member->fresh()->can('vehicles.view'))->toBeTrue()
        ->and($member->fresh()->can('trips.view'))->toBeFalse()
        ->and($member->fresh()->can('users.view'))->toBeFalse();
});

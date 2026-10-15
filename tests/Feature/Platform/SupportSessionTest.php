<?php

use App\Http\Middleware\EnsurePlatformSession;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\PlatformSupportSession;
use App\Models\User;
use App\Notifications\SupportSessionNotification;
use App\Services\Platform\PlatformCompanyService;
use App\Services\Platform\PlatformStaffService;
use App\Services\Platform\SupportSessionService;
use App\Tenancy\TenantContext;
use Database\Factories\PlatformUserFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Support\Actors;

/*
| Support sessions (Q1, 04 §4): the only way into a company's data. The company Owner and a
| second Platform Owner both approve; the requester starts it as the company's support user with
| a locked Support role; every request is audited; the Owner can end it at any time.
*/

beforeEach(function () {
    Notification::fake();
    $this->company = Actors::company();
    $this->owner = $this->company->owner;
    $this->staff = Actors::platformOwner();
    $this->approver = Actors::platformOwner();
    $this->sessions = app(SupportSessionService::class);
});

function supportUserOf(Company $company): User
{
    return User::query()->where('company_id', $company->id)->where('user_type', 'support')->sole();
}

function supportRolesOf(User $user): array
{
    return DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $user->id)->where('model_has_roles.model_type', $user->getMorphClass())
        ->pluck('roles.system_key')->all();
}

describe('asking and approving', function () {
    it('lets staff ask through the platform screen and emails the owner', function () {
        $this->actingAs($this->staff, 'platform')
            ->post(route('platform.support-sessions.store'), ['company_id' => $this->company->id, 'scope' => 'read', 'reason' => 'Ticket 77: missing driver file'])
            ->assertRedirect(route('platform.support-sessions.index'));

        $session = PlatformSupportSession::query()->sole();
        expect([$session->status, $session->scope, $session->platform_user_id])->toBe(['requested', 'read', $this->staff->id])
            ->and(AuditLog::where('action', 'support_session.requested')->where('support_session_id', $session->id)->exists())->toBeTrue();

        Notification::assertSentTo($this->owner, SupportSessionNotification::class, fn ($n) => $n->event === 'requested');
    });

    it('needs both approvals, in either order, before it can start', function (bool $ownerFirst) {
        $session = $this->sessions->request($this->staff, $this->company, 'read', 'Ticket 1: help please');
        $approve = [
            fn () => $this->sessions->approveAsTenant($this->owner, $session),
            fn () => $this->sessions->approveAsPlatform($this->approver, $session),
        ];
        $ownerFirst ? $approve[0]() : $approve[1]();

        expect($session->refresh()->status)->toBe('requested')
            ->and(fn () => $this->sessions->start($this->staff, $session))->toThrow(ValidationException::class);

        $ownerFirst ? $approve[1]() : $approve[0]();
        expect($session->refresh()->status)->toBe('approved');
    })->with(['owner first' => [true], 'platform first' => [false]]);

    it('lets the owner approve from their support access page', function () {
        $session = $this->sessions->request($this->staff, $this->company, 'read', 'Ticket 2: help please');

        $this->actingAs($this->owner)->get(route('support-access.index'))->assertOk()->assertSee('Ticket 2: help please');
        $this->actingAs($this->owner)->post(route('support-access.approve', $session))->assertRedirect(route('support-access.index'));

        expect($session->refresh()->tenant_approved_by_user_id)->toBe($this->owner->id);
    });

    it('refuses the requester as the second approver (four-eyes), in the service and in the database', function () {
        $session = $this->sessions->request($this->staff, $this->company, 'read', 'Ticket 3: help please');

        expect(fn () => $this->sessions->approveAsPlatform($this->staff, $session))->toThrow(AuthorizationException::class)
            ->and(fn () => DB::table('platform_support_sessions')->where('id', $session->id)->update(['platform_approved_by_platform_user_id' => $this->staff->id]))
            ->toThrow(QueryException::class);
    });

    it('lets only a Platform Owner approve, and only while two active Platform Owners exist', function () {
        $session = $this->sessions->request($this->staff, $this->company, 'read', 'Ticket 4: help please');

        $this->actingAs(Actors::platformSupport(), 'platform')
            ->post(route('platform.support-sessions.approve', $session))->assertForbidden();

        $this->approver->forceFill(['status' => 'inactive'])->save();
        $third = PlatformUserFactory::new()->create();
        DB::table('model_has_roles')->insert(['role_id' => DB::table('roles')->where('team_id', 0)->where('system_key', 'platform_owner')->value('id'), 'model_type' => $third->getMorphClass(), 'model_id' => $third->id, 'team_id' => 0]);
        $this->staff->forceFill(['status' => 'inactive'])->save();

        expect(fn () => $this->sessions->approveAsPlatform($third, $session))->toThrow(AuthorizationException::class, 'two active Platform Owners');
    });

    it('lets only the owner approve for the company, and hides other companies\' sessions', function () {
        $session = $this->sessions->request($this->staff, $this->company, 'read', 'Ticket 5: help please');

        $this->actingAs(Actors::companyAdmin($this->company))->post(route('support-access.approve', $session))->assertForbidden();
        $this->actingAs(Actors::companyOwner())->post(route('support-access.approve', $session))->assertNotFound();

        expect($session->refresh()->tenant_approved_by_user_id)->toBeNull();
    });

    it('lets either side refuse, after which it can not be approved', function () {
        $session = $this->sessions->request($this->staff, $this->company, 'read', 'Ticket 6: help please');

        $this->actingAs($this->owner)->post(route('support-access.reject', $session))->assertRedirect();

        expect($session->refresh()->status)->toBe('rejected')
            ->and($session->rejected_by_type)->toBe('user')
            ->and(fn () => $this->sessions->approveAsPlatform($this->approver, $session))->toThrow(ValidationException::class);
    });

    it('expires a request nobody fully approved within the window', function () {
        $session = $this->sessions->request($this->staff, $this->company, 'read', 'Ticket 7: help please');
        $this->sessions->approveAsTenant($this->owner, $session);
        $this->travel(PlatformSupportSession::REQUEST_WINDOW_HOURS + 1)->hours();

        Artisan::call('support-sessions:expire');

        expect($session->refresh()->status)->toBe('expired')
            ->and(AuditLog::where('action', 'support_session.expired')->where('support_session_id', $session->id)->where('actor_type', 'system')->exists())->toBeTrue();
    });

    it('can not start once the company is no longer active', function () {
        $session = $this->sessions->request($this->staff, $this->company, 'read', 'Ticket 12: help please');
        $this->sessions->approveAsTenant($this->owner, $session);
        $this->sessions->approveAsPlatform($this->approver, $session);
        $this->company->forceFill(['status' => 'suspended'])->save();

        expect(fn () => $this->sessions->start($this->staff, $session))->toThrow(ValidationException::class, 'not active')
            ->and(supportUserOf($this->company)->status)->toBe('inactive');
    });
});

describe('a started session', function () {
    it('logs the requester in as the support user with the locked role, shows the banner and tells the owner', function () {
        $session = Actors::inSupportSession($this->company, 'read', $this->staff);
        $support = supportUserOf($this->company);

        expect($support->status)->toBe('active')
            ->and(supportRolesOf($support))->toBe(['support_read'])
            ->and($session->expires_at->diffInMinutes($session->started_at, true))->toEqual(240.0);

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Support session')->assertSee($session->requester->name);

        Notification::assertSentTo($this->owner, SupportSessionNotification::class, fn ($n) => $n->event === 'started');
    });

    it('starts through the platform screen and lands in the tenant area', function () {
        $session = $this->sessions->request($this->staff, $this->company, 'read_write', 'Ticket 8: help please');
        $this->sessions->approveAsTenant($this->owner, $session);
        $this->sessions->approveAsPlatform($this->approver, $session);

        $this->actingAs($this->staff, 'platform')
            ->post(route('platform.support-sessions.start', $session))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs(supportUserOf($this->company), 'web');
        $this->assertAuthenticatedAs($this->staff, 'platform');
        expect(session(SupportSessionService::SESSION_KEY))->toBe($session->id);
    });

    it('can only be started by the requester, and one at a time per company', function () {
        $first = Actors::inSupportSession($this->company, 'read_write', $this->staff);
        $second = $this->sessions->request($this->approver, $this->company, 'read', 'Ticket 9: help please');
        $this->sessions->approveAsTenant($this->owner, $second);
        $this->sessions->approveAsPlatform($this->staff, $second);

        expect(fn () => $this->sessions->start($this->staff, $second))->toThrow(AuthorizationException::class)
            ->and(fn () => $this->sessions->start($this->approver, $second))->toThrow(ValidationException::class, 'Another support session');
        expect($first->refresh()->status)->toBe('active');
    });

    it('refuses every write in a read session', function () {
        Actors::inSupportSession($this->company, 'read', $this->staff);

        $this->get(route('admin.driver.index'))->assertOk();
        $this->get(route('admin.driver.create'))->assertForbidden();
        $this->postJson(route('admin.vehicle.store'), [])->assertForbidden();
    });

    it('keeps billing, users, roles and deletes out of a read_write session', function (string $method, string $route, array $params) {
        Actors::inSupportSession($this->company, 'read_write', $this->staff);

        $this->json($method, route($route, $params))->assertForbidden();
    })->with([
        'billing' => ['GET', 'billing.index', []],
        'billing portal' => ['POST', 'billing.portal', []],
        'users' => ['GET', 'users.index', []],
        'invite' => ['POST', 'users.invitations.store', []],
        'roles' => ['GET', 'admin.roles.index', []],
        'delete driver' => ['DELETE', 'admin.driver.destroy', ['id' => 1]],
        'delete driver document' => ['DELETE', 'admin.compliance.driver.documents.delete', []],
        'delete fleet document' => ['DELETE', 'admin.compliance.documents.delete', []],
    ]);

    it('lets a read_write session change records', function () {
        Actors::inSupportSession($this->company, 'read_write', $this->staff);

        $this->get(route('admin.driver.create'))->assertOk();
    });

    it('audits every request with the staff member and the session', function () {
        $session = Actors::inSupportSession($this->company, 'read', $this->staff);

        $this->get(route('admin.driver.index'))->assertOk();
        $this->get(route('admin.driver.create'))->assertForbidden();

        $rows = AuditLog::where('action', 'support_session.request')->orderBy('id')->get();
        expect($rows)->toHaveCount(2)
            ->and($rows->every(fn ($r) => $r->actor_type === 'platform_user' && $r->actor_id === $this->staff->id
                && $r->support_session_id === $session->id && $r->company_id === $this->company->id))->toBeTrue()
            // MySQL's JSON type reorders keys, so compare content, not order
            ->and($rows[0]->changes)->toEqual(['method' => 'GET', 'route' => 'admin.driver.index', 'path' => '/admin/driver', 'status' => 200])
            ->and($rows[1]->changes['status'])->toBe(403);
    });

    it('attributes a tenant change made in the session to the staff member', function () {
        $session = Actors::inSupportSession($this->company, 'read_write', $this->staff);
        $support = supportUserOf($this->company);

        app(TenantContext::class)->run($this->company->id,
            fn () => AuditLog::record($support, $this->company->id, 'test.change', $support), 'support_session', $session->id);

        $row = AuditLog::where('action', 'test.change')->sole();
        expect([$row->actor_type, $row->actor_id, $row->support_session_id])->toBe(['platform_user', $this->staff->id, $session->id]);
    });
});

describe('ending', function () {
    it('ends at once when the owner revokes it, and the next request is out', function () {
        $session = Actors::inSupportSession($this->company, 'read_write', $this->staff);
        $this->get(route('admin.driver.index'))->assertOk();

        $this->sessions->revoke($this->owner, $session);

        $this->get(route('admin.driver.index'))->assertRedirect(route('platform.support-sessions.index'));
        expect($session->refresh()->status)->toBe('ended')
            ->and($session->revoked_by_user_id)->toBe($this->owner->id)
            ->and($this->isAuthenticated('web'))->toBeFalse()
            // The staff member keeps their platform login
            ->and($this->isAuthenticated('platform'))->toBeTrue();
        Notification::assertSentTo($this->owner, SupportSessionNotification::class, fn ($n) => $n->event === 'ended');
    });

    it('ends when its time is up, on the next request and from the scheduler', function () {
        $first = Actors::inSupportSession($this->company, 'read_write', $this->staff);
        $this->travel(241)->minutes();

        $this->get(route('admin.driver.index'))->assertRedirect(route('platform.support-sessions.index'));
        expect($first->refresh()->end_reason)->toBe('expired');

        $other = Actors::company();
        $second = Actors::inSupportSession($other);
        $this->travel(241)->minutes();
        Artisan::call('support-sessions:expire');
        expect($second->refresh()->status)->toBe('ended');
    });

    it('ends when the staff member ends it, logs out of the platform or is disabled', function (Closure $end) {
        $session = Actors::inSupportSession($this->company, 'read_write', $this->staff);

        $end($this, $session);

        $support = supportUserOf($this->company);
        expect($session->refresh()->status)->toBe('ended')
            ->and($support->status)->toBe('inactive')
            ->and(supportRolesOf($support))->toBe([]);
    })->with([
        'staff ends it' => [fn ($t, $s) => $t->post(route('platform.support-sessions.end', $s))->assertRedirect(route('platform.support-sessions.index'))],
        'platform logout' => [fn ($t) => $t->post(route('platform.logout'))],
        'staff disabled' => [fn ($t) => app(PlatformStaffService::class)->setStatus(Actors::platformOwner(), $t->staff, 'inactive')],
        'company suspended' => [fn ($t) => app(PlatformCompanyService::class)->setStatus($t->approver, $t->company, 'suspended', 'test')],
    ]);
});

describe('ending from the tenant side', function () {
    it('ends the session, not the platform login, when the company stops being active mid-session', function () {
        $session = Actors::inSupportSession($this->company, 'read_write', $this->staff);
        DB::table('companies')->where('id', $this->company->id)->update(['status' => 'deactivated']);

        $this->get(route('admin.driver.index'))->assertRedirect(route('platform.support-sessions.index'));

        expect($session->refresh()->end_reason)->toBe('company not active')
            ->and($this->isAuthenticated('platform'))->toBeTrue();
    });

    it('ends the session, not the platform login, on the tenant logout button', function () {
        $session = Actors::inSupportSession($this->company, 'read_write', $this->staff);

        $this->post(route('logout'))->assertRedirect(route('platform.support-sessions.index'));

        expect($session->refresh()->status)->toBe('ended')
            ->and($this->isAuthenticated('web'))->toBeFalse()
            ->and($this->isAuthenticated('platform'))->toBeTrue();
    });

    it('ends the session when the platform login has been idle too long', function () {
        $session = Actors::inSupportSession($this->company, 'read_write', $this->staff);

        $this->withSession([EnsurePlatformSession::LAST_SEEN => time() - (config('auth.platform_idle_minutes') + 1) * 60])
            ->get(route('admin.driver.index'))->assertRedirect(route('platform.support-sessions.index'));

        expect($session->refresh()->end_reason)->toBe('platform login idle');
    });
});

describe('hardening', function () {
    it('never changes the support user\'s own account, even in a read_write session', function () {
        Actors::inSupportSession($this->company, 'read_write', $this->staff);
        $support = supportUserOf($this->company);

        $this->patch(route('profile.update'), ['name' => 'X', 'email' => 'taken-by-support@example.com'])->assertForbidden();
        $this->put(route('password.update'), ['current_password' => 'x', 'password' => 'Password123!', 'password_confirmation' => 'Password123!'])->assertForbidden();

        expect($support->fresh()->email)->toBe($support->email);
    });

    it('never lets the support user log in with a password, even during a session', function () {
        $support = supportUserOf($this->company);
        $support->forceFill(['password' => 'Password123!', 'status' => 'active'])->save();

        $this->post('/login', ['email' => $support->email, 'password' => 'Password123!'])->assertSessionHasErrors('email');

        expect($this->isAuthenticated('web'))->toBeFalse();
    });

    it('logs out a support user that has no valid session', function () {
        Actors::inSupportSession($this->company, 'read_write', $this->staff);

        $this->withSession([SupportSessionService::SESSION_KEY => null])
            ->get(route('admin.driver.index'))
            ->assertRedirect(route('platform.support-sessions.index'));
    });

    it('ignores the support login when another staff member holds the browser', function () {
        Actors::inSupportSession($this->company, 'read_write', $this->staff);
        $this->actingAs($this->approver, 'platform')->actingAs(supportUserOf($this->company), 'web');

        $this->get(route('admin.driver.index'))->assertRedirect(route('platform.support-sessions.index'));
    });

    it('shows the owner page only to the owner', function () {
        $this->actingAs(Actors::companyAdmin($this->company))->get(route('support-access.index'))->assertForbidden();
    });

    it('keeps one active session per company in the database', function () {
        Actors::inSupportSession($this->company, 'read_write', $this->staff);
        $second = $this->sessions->request($this->approver, $this->company, 'read', 'Ticket 10: help please');
        $this->sessions->approveAsTenant($this->owner, $second);
        $this->sessions->approveAsPlatform($this->staff, $second);

        expect(fn () => DB::table('platform_support_sessions')->where('id', $second->id)->update(['status' => 'active']))
            ->toThrow(QueryException::class);
    });

    it('treats a session id of another company as not found for the owner', function () {
        $session = $this->sessions->request($this->staff, Actors::company(), 'read', 'Ticket 11: help please');

        expect(fn () => $this->sessions->approveAsTenant($this->owner, $session))->toThrow(ModelNotFoundException::class);
    });
});

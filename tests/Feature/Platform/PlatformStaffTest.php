<?php

use App\Models\AuditLog;
use App\Models\PlatformUser;
use App\Models\Role;
use App\Notifications\PlatformResetPasswordNotification;
use App\Services\Platform\PlatformStaffService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Actors;

/*
| Platform staff (04 §3): invited by a Platform Owner, never editing themselves, and at least
| two active Platform Owners at all times. The first two come from platform:bootstrap-owners.
*/

beforeEach(function () {
    Notification::fake();
});

function platformRoleId(string $systemKey): int
{
    return (int) Role::query()->where('team_id', 0)->where('system_key', $systemKey)->value('id');
}

function heldPlatformRoles(PlatformUser $staff): array
{
    return app(PlatformStaffService::class)->heldRoleIds($staff);
}

it('invites staff with their roles and emails a link to set their password', function () {
    $this->actingAs(Actors::platformOwner(), 'platform')
        ->post(route('platform.staff.store'), ['name' => 'Agent', 'email' => 'Agent@Example.com', 'roles' => [platformRoleId('platform_support')]])
        ->assertRedirect(route('platform.staff.index'));

    $agent = PlatformUser::query()->where('email', 'agent@example.com')->sole();
    expect(heldPlatformRoles($agent))->toBe([platformRoleId('platform_support')])
        ->and(AuditLog::where('action', 'platform_user.invited')->where('subject_id', $agent->id)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'role.assigned')->where('subject_id', $agent->id)->exists())->toBeTrue();

    Notification::assertSentTo($agent, PlatformResetPasswordNotification::class);
});

it('changes the roles of a staff member', function () {
    $agent = Actors::platformSupport();

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->put(route('platform.staff.update', $agent), ['name' => 'Agent', 'email' => $agent->email, 'roles' => [platformRoleId('platform_admin')]])
        ->assertSessionHasNoErrors();

    expect(heldPlatformRoles($agent))->toBe([platformRoleId('platform_admin')]);
});

describe('at least two active Platform Owners', function () {
    beforeEach(function () {
        $this->owner = Actors::platformOwner();
        $this->other = Actors::platformOwner();
    });

    it('refuses to demote one of the last two', function () {
        $this->actingAs($this->owner, 'platform')
            ->put(route('platform.staff.update', $this->other), ['name' => 'X', 'email' => $this->other->email, 'roles' => [platformRoleId('platform_admin')]])
            ->assertSessionHasErrors('roles');

        expect(heldPlatformRoles($this->other))->toBe([platformRoleId('platform_owner')]);
    });

    it('refuses to disable one of the last two', function () {
        $this->actingAs($this->owner, 'platform')
            ->post(route('platform.staff.status', $this->other), ['status' => 'inactive'])
            ->assertSessionHasErrors('status');

        expect($this->other->fresh()->status)->toBe('active');
    });

    it('allows it once a third active owner exists', function () {
        Actors::platformOwner();

        $this->actingAs($this->owner, 'platform')
            ->post(route('platform.staff.status', $this->other), ['status' => 'inactive'])
            ->assertSessionHasNoErrors();

        expect($this->other->fresh()->status)->toBe('inactive')
            ->and(AuditLog::where('action', 'platform_user.status_changed')->where('subject_id', $this->other->id)->exists())->toBeTrue();
    });

    it('does not count an inactive owner', function () {
        Actors::platformOwner(['status' => 'inactive']);

        $this->actingAs($this->owner, 'platform')
            ->post(route('platform.staff.status', $this->other), ['status' => 'inactive'])
            ->assertSessionHasErrors('status');
    });

    it('lets nobody change their own account', function () {
        $this->actingAs($this->owner, 'platform')
            ->post(route('platform.staff.status', $this->owner), ['status' => 'inactive'])
            ->assertSessionHasErrors('status');
        $this->put(route('platform.staff.update', $this->owner), ['name' => 'Me', 'email' => $this->owner->email, 'roles' => [platformRoleId('platform_owner')]])
            ->assertSessionHasErrors('roles');

        expect($this->owner->fresh()->status)->toBe('active');
    });
});

it('is only for staff with platform.staff.manage', function () {
    $this->actingAs(Actors::platformStaff('platform_admin'), 'platform')
        ->post(route('platform.staff.store'), ['name' => 'X', 'email' => 'x@example.com', 'roles' => [platformRoleId('platform_owner')]])
        ->assertForbidden();

    expect(PlatformUser::query()->where('email', 'x@example.com')->exists())->toBeFalse();
});

describe('platform:bootstrap-owners', function () {
    it('creates two Platform Owners and emails each a reset link', function () {
        expect(Artisan::call('platform:bootstrap-owners', ['email1' => 'One@Example.com', 'name1' => 'One', 'email2' => 'two@example.com', 'name2' => 'Two']))->toBe(0);

        $owners = PlatformUser::query()->orderBy('id')->get();
        expect($owners->pluck('email')->all())->toBe(['one@example.com', 'two@example.com'])
            ->and($owners->every(fn (PlatformUser $u) => heldPlatformRoles($u) === [platformRoleId('platform_owner')]))->toBeTrue()
            ->and(AuditLog::where('action', 'platform.owner_bootstrapped')->where('actor_type', 'system')->count())->toBe(2);

        Notification::assertSentTo($owners, PlatformResetPasswordNotification::class);
    });

    it('runs only once', function () {
        Actors::platformOwner();

        expect(Artisan::call('platform:bootstrap-owners', ['email1' => 'a@example.com', 'name1' => 'A', 'email2' => 'b@example.com', 'name2' => 'B']))->toBe(1)
            ->and(PlatformUser::query()->count())->toBe(1);
    });

    it('needs two different valid emails', function () {
        expect(Artisan::call('platform:bootstrap-owners', ['email1' => 'a@example.com', 'name1' => 'A', 'email2' => 'A@example.com', 'name2' => 'B']))->toBe(1)
            ->and(Artisan::call('platform:bootstrap-owners', ['email1' => 'not-an-email', 'name1' => 'A', 'email2' => 'b@example.com', 'name2' => 'B']))->toBe(1)
            ->and(DB::table('platform_users')->count())->toBe(0);
    });
});

<?php

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\Role;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\Actors;

/*
| USR-01, changed by design (11 §5): Owners and Administrators manage the users of their own
| company. Another company's ids are 404, and the 06 §8 rules hold over HTTP.
*/

function teamRoleId(Company $company, string $name): int
{
    return (int) Role::query()->where('team_id', $company->id)->where('name', $name)->value('id');
}

function heldRoleNames(User $user): array
{
    return DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $user->id)
        ->where('model_has_roles.team_id', $user->company_id)
        ->orderBy('roles.name')
        ->pluck('roles.name')
        ->all();
}

describe('own company', function () {
    it('lets the Owner and an Administrator list their own users only', function () {
        $company = Actors::company();
        $dispatcher = Actors::dispatcher($company);
        $other = Actors::dispatcher();
        $support = UserFactory::new()->create(['company_id' => $company->id, 'user_type' => 'support']);

        foreach ([$company->owner, Actors::companyAdmin($company)] as $actor) {
            $this->actingAs($actor)->get(route('users.index'))
                ->assertOk()
                ->assertSee($dispatcher->email)
                ->assertDontSee($other->email)
                ->assertDontSee($support->email);
        }
    });

    it('lets an Administrator rename a user and change their roles', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs(Actors::companyAdmin($company))
            ->put(route('users.update', $target->id), [
                'name' => 'Renamed',
                'roles' => [teamRoleId($company, 'Office / Compliance')],
            ])
            ->assertRedirect(route('users.index'));

        expect($target->fresh()->name)->toBe('Renamed')
            ->and(heldRoleNames($target))->toBe(['Office / Compliance']);
    });

    it('keeps locked roles the target holds when syncing roles', function () {
        $company = Actors::company();
        $driver = Actors::driverUser($company);

        $this->actingAs($company->owner)
            ->put(route('users.update', $driver->id), [
                'name' => $driver->name,
                'roles' => [teamRoleId($company, 'Dispatcher')],
            ])
            ->assertRedirect(route('users.index'));

        expect(heldRoleNames($driver))->toBe(['Dispatcher', 'Driver']);
    });

    it('lets the Owner deactivate and reactivate a user', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs($company->owner)->post(route('users.deactivate', $target->id))->assertRedirect(route('users.index'));
        expect($target->fresh()->status)->toBe('inactive');

        $this->actingAs($company->owner)->post(route('users.reactivate', $target->id))->assertRedirect(route('users.index'));
        expect($target->fresh()->status)->toBe('active');
    });
});

describe('cross-company access', function () {
    it('returns 404 for another company\'s user on every user route', function (string $method, string $route) {
        $target = Actors::dispatcher();

        $this->actingAs(Actors::companyOwner())
            ->call($method, route($route, $target->id), ['name' => 'Hacked', 'roles' => [], 'permission' => 'drivers.view'])
            ->assertNotFound();

        expect($target->fresh()->name)->not->toBe('Hacked')
            ->and($target->fresh()->status)->toBe('active');
    })->with([
        'edit' => ['GET', 'users.edit'],
        'update' => ['PUT', 'users.update'],
        'deactivate' => ['POST', 'users.deactivate'],
        'reactivate' => ['POST', 'users.reactivate'],
        'grant' => ['POST', 'users.permissions.store'],
    ]);

    it('returns 404 when revoking a permission of another company\'s user', function () {
        $target = Actors::dispatcher();

        $this->actingAs(Actors::companyOwner())
            ->delete(route('users.permissions.destroy', [$target->id, 'trips.view']))
            ->assertNotFound();
    });

    it('returns 404 for another company\'s invitation', function (string $method, string $route) {
        $other = Actors::company();
        $this->actingAs($other->owner)->post(route('users.invitations.store'), [
            'email' => 'new@example.com',
            'role_id' => teamRoleId($other, 'Dispatcher'),
        ]);
        $invitation = CompanyInvitation::withoutTenancy()->firstOrFail();

        $this->actingAs(Actors::companyOwner())->call($method, route($route, $invitation->id))->assertNotFound();

        expect($invitation->fresh()->revoked_at)->toBeNull();
    })->with([
        'resend' => ['POST', 'users.invitations.resend'],
        'revoke' => ['DELETE', 'users.invitations.destroy'],
    ]);

    it('returns 404 when assigning another company\'s role', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);
        $foreignRole = teamRoleId(Actors::company(), 'Administrator');

        $this->actingAs($company->owner)
            ->put(route('users.update', $target->id), ['name' => $target->name, 'roles' => [$foreignRole]])
            ->assertNotFound();

        expect(heldRoleNames($target))->toBe(['Dispatcher']);
    });
});

describe('escalation over HTTP (06 §8)', function () {
    it('forbids a user without users permissions from every users route', function (string $method, string $route, bool $needsId) {
        $company = Actors::company();
        $target = Actors::officeUser($company);
        $url = $needsId ? route($route, $target->id) : route($route);

        $this->actingAs(Actors::dispatcher($company))
            ->call($method, $url, ['name' => 'Hacked', 'email' => 'x@example.com', 'role_id' => 1])
            ->assertForbidden();

        expect($target->fresh()->name)->not->toBe('Hacked');
    })->with([
        'index' => ['GET', 'users.index', false],
        'invite form' => ['GET', 'users.invite', false],
        'invite' => ['POST', 'users.invitations.store', false],
        'edit' => ['GET', 'users.edit', true],
        'update' => ['PUT', 'users.update', true],
        'deactivate' => ['POST', 'users.deactivate', true],
        'grant' => ['POST', 'users.permissions.store', true],
    ]);

    it('does not let anyone change their own roles or status', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);

        $this->actingAs($admin)
            ->put(route('users.update', $admin->id), ['name' => $admin->name, 'roles' => []])
            ->assertForbidden();
        $this->actingAs($admin)->post(route('users.deactivate', $admin->id))->assertForbidden();

        expect(heldRoleNames($admin))->toBe(['Administrator'])
            ->and($admin->fresh()->status)->toBe('active');
    });

    it('does not let an Administrator change or deactivate the Owner', function () {
        $company = Actors::company();
        $owner = $company->owner;

        $this->actingAs(Actors::companyAdmin($company))
            ->put(route('users.update', $owner->id), ['name' => 'Renamed', 'roles' => []])
            ->assertForbidden();
        $this->actingAs(Actors::companyAdmin($company))->post(route('users.deactivate', $owner->id))->assertForbidden();

        expect($owner->fresh()->name)->not->toBe('Renamed')
            ->and($owner->fresh()->status)->toBe('active')
            ->and(heldRoleNames($owner))->toBe(['Owner']);
    });

    it('refuses to assign a locked role', function (string $locked) {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs($company->owner)
            ->put(route('users.update', $target->id), ['name' => $target->name, 'roles' => [teamRoleId($company, $locked)]])
            ->assertForbidden();

        expect(heldRoleNames($target))->toBe(['Dispatcher']);
    })->with(['Owner', 'Driver', 'Support (read)']);

    it('refuses to assign a role with permissions the actor does not hold', function () {
        $company = Actors::company();
        $manager = Actors::memberWithPermissions($company, ['users.view', 'users.edit']);
        $target = Actors::dispatcher($company);

        $this->actingAs($manager)
            ->put(route('users.update', $target->id), ['name' => $target->name, 'roles' => [teamRoleId($company, 'Administrator')]])
            ->assertForbidden();

        expect(heldRoleNames($target))->toBe(['Dispatcher']);
    });

    it('keeps roles the actor cannot assign when saving the edit form', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);

        $this->actingAs(Actors::memberWithPermissions($company, ['users.view', 'users.edit']))
            ->put(route('users.update', $target->id), ['name' => 'Renamed', 'roles' => []])
            ->assertRedirect(route('users.index'));

        expect($target->fresh()->name)->toBe('Renamed')
            ->and(heldRoleNames($target))->toBe(['Dispatcher']);
    });

    it('only offers roles the actor may assign on the edit form', function () {
        $company = Actors::company();
        $target = Actors::dispatcher($company);
        $checkbox = fn (string $role) => 'name="roles[]" value="'.teamRoleId($company, $role).'"';

        $this->actingAs(Actors::companyAdmin($company))
            ->get(route('users.edit', $target->id))
            ->assertOk()
            ->assertSee($checkbox('Dispatcher'), false)
            ->assertSee($checkbox('Administrator'), false)
            ->assertDontSee($checkbox('Owner'), false)
            ->assertDontSee($checkbox('Driver'), false);

        $this->actingAs(Actors::memberWithPermissions($company, ['users.view', 'users.edit', 'trips.view']))
            ->get(route('users.edit', $target->id))
            ->assertOk()
            ->assertDontSee($checkbox('Dispatcher'), false)
            ->assertDontSee($checkbox('Administrator'), false);
    });
});

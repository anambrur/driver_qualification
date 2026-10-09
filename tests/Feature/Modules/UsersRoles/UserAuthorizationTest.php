<?php

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\Support\Actors;

/*
| USR-01: /users/* used to be guarded by `auth` only. Any logged-in tenant could list every
| user, edit anyone's email/password/roles, and make themselves super-admin.
| The `users.*` permissions belong to super-admin only (PermissionSeeder).
*/

function superAdminRoleId(): int
{
    Actors::seedAccessControl();

    return Role::findByName('super-admin', 'web')->id;
}

function userPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'name' => $user->name,
        'email' => $user->email,
        'status' => 'active',
    ], $overrides);
}

describe('USR-01: only users with users.* permissions can manage users', function () {
    it('does not let a tenant make themselves super-admin', function () {
        $tenant = Actors::companyOwner();

        $this->actingAs($tenant)
            ->put(route('users.update', $tenant->id), userPayload($tenant, ['roles' => [superAdminRoleId()]]))
            ->assertForbidden();

        expect($tenant->fresh()->hasRole('super-admin'))->toBeFalse();
    });

    it("does not let a tenant change another user's email or password", function () {
        $victim = Actors::superAdmin(['email' => 'boss@example.com']);
        $oldHash = $victim->password;

        $this->actingAs(Actors::companyOwner())
            ->put(route('users.update', $victim->id), userPayload($victim, [
                'email' => 'attacker@example.com',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ]))
            ->assertForbidden();

        $victim->refresh();
        expect($victim->email)->toBe('boss@example.com')
            ->and($victim->password)->toBe($oldHash);
    });

    it('forbids a user without users permissions from every users route', function (string $method, string $route, bool $needsId) {
        $target = Actors::companyOwner();
        $url = $needsId ? route($route, $target->id) : route($route);

        foreach ([Actors::companyOwner(), Actors::plainUser()] as $actor) {
            $this->actingAs($actor)->call($method, $url, userPayload($target))->assertForbidden();
        }

        expect(User::query()->whereKey($target->id)->exists())->toBeTrue();
    })->with([
        'index' => ['GET', 'users.index', false],
        'create' => ['GET', 'users.create', false],
        'store' => ['POST', 'users.store', false],
        'edit' => ['GET', 'users.edit', true],
        'update' => ['PUT', 'users.update', true],
        'destroy' => ['DELETE', 'users.destroy', true],
        'suspend' => ['POST', 'users.suspend', true],
        'unsuspend' => ['POST', 'users.unsuspend', true],
    ]);

    it('does not let a non-super-admin with users.edit grant the super-admin role', function () {
        $manager = Actors::plainUser(['users.view', 'users.edit', 'users.create']);
        $target = Actors::companyOwner();

        $this->actingAs($manager)
            ->put(route('users.update', $manager->id), userPayload($manager, ['roles' => [superAdminRoleId()]]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->put(route('users.update', $target->id), userPayload($target, ['roles' => [superAdminRoleId()]]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('users.store'), [
                'name' => 'Sneaky',
                'email' => 'sneaky@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'roles' => [superAdminRoleId()],
            ])
            ->assertForbidden();

        expect($manager->fresh()->hasRole('super-admin'))->toBeFalse()
            ->and($target->fresh()->hasRole('super-admin'))->toBeFalse()
            ->and(User::query()->where('email', 'sneaky@example.com')->exists())->toBeFalse();
    });

    it('does not let a non-super-admin with users.edit change or delete a super-admin', function () {
        $manager = Actors::plainUser(['users.view', 'users.edit', 'users.delete']);
        $boss = Actors::superAdmin(['email' => 'boss@example.com']);

        $this->actingAs($manager)
            ->put(route('users.update', $boss->id), userPayload($boss, ['email' => 'attacker@example.com']))
            ->assertForbidden();

        $this->actingAs($manager)->delete(route('users.destroy', $boss->id))->assertForbidden();

        expect($boss->fresh()->email)->toBe('boss@example.com');
    });
});

describe('USR-01: a super-admin cannot remove their own super-admin role', function () {
    it('refuses to drop super-admin from your own roles', function () {
        $admin = Actors::superAdmin();
        $companyRoleId = Role::findByName('company', 'web')->id;

        $this->actingAs($admin)
            ->from(route('users.edit', $admin->id))
            ->put(route('users.update', $admin->id), userPayload($admin, ['roles' => [$companyRoleId]]))
            ->assertRedirect(route('users.edit', $admin->id));

        expect($admin->fresh()->hasRole('super-admin'))->toBeTrue()
            ->and($admin->fresh()->hasRole('company'))->toBeFalse();
    });

    it('lets you keep super-admin while adding other roles to yourself', function () {
        $admin = Actors::superAdmin();
        $companyRoleId = Role::findByName('company', 'web')->id;

        $this->actingAs($admin)
            ->put(route('users.update', $admin->id), userPayload($admin, ['roles' => [superAdminRoleId(), $companyRoleId]]))
            ->assertRedirect(route('users.index'));

        expect($admin->fresh()->hasRole('super-admin'))->toBeTrue()
            ->and($admin->fresh()->hasRole('company'))->toBeTrue();
    });

    it('still lets a super-admin demote another super-admin', function () {
        $admin = Actors::superAdmin();
        $other = Actors::superAdmin();

        $this->actingAs($admin)
            ->put(route('users.update', $other->id), userPayload($other, ['roles' => [Role::findByName('company', 'web')->id]]))
            ->assertRedirect(route('users.index'));

        expect($other->fresh()->hasRole('super-admin'))->toBeFalse();
    });
});

describe('USR-01: super-admin keeps full control (regression guard)', function () {
    it('lets a super-admin list, create, update and delete users', function () {
        $admin = Actors::superAdmin();
        $tenant = Actors::companyOwner();

        $this->actingAs($admin)->get(route('users.index'))->assertOk();
        $this->actingAs($admin)->get(route('users.create'))->assertOk();
        $this->actingAs($admin)->get(route('users.edit', $tenant->id))->assertOk();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'New Admin',
                'email' => 'new-admin@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'roles' => [superAdminRoleId()],
            ])
            ->assertRedirect(route('users.index'));

        expect(User::query()->where('email', 'new-admin@example.com')->first()->hasRole('super-admin'))->toBeTrue();

        $this->actingAs($admin)
            ->put(route('users.update', $tenant->id), userPayload($tenant, ['name' => 'Renamed']))
            ->assertRedirect(route('users.index'));

        expect($tenant->fresh()->name)->toBe('Renamed');

        $this->actingAs($admin)->delete(route('users.destroy', $tenant->id))->assertRedirect(route('users.index'));

        expect(User::query()->whereKey($tenant->id)->exists())->toBeFalse();
    });
});

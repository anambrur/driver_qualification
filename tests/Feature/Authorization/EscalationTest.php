<?php

use App\Authorization\TeamScope;
use App\Authorization\TenantPermissionResolver;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\OwnershipService;
use App\Services\Authorization\RoleAssignmentService;
use App\Services\Authorization\RoleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Tests\Support\Actors;

/*
| 06 §8 escalation rules, enforced by RoleService, RoleAssignmentService and OwnershipService.
*/

function roleId(Company $company, string $name): int
{
    return (int) Role::query()->where('team_id', $company->id)->where('name', $name)->value('id');
}

function roles(): RoleService
{
    return app(RoleService::class);
}

function assignments(): RoleAssignmentService
{
    return app(RoleAssignmentService::class);
}

describe('RoleService', function () {
    it('lets an Administrator create a role from permissions they hold, and audits it', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);

        $role = roles()->create($admin, 'Yard', ['vehicles.view', 'trailers.view']);

        expect($role->team_id)->toBe($company->id)
            ->and($role->permissions()->pluck('name')->sort()->values()->all())->toBe(['trailers.view', 'vehicles.view'])
            ->and(AuditLog::where('action', 'role.created')->where('subject_id', $role->id)->where('company_id', $company->id)->exists())->toBeTrue();
    });

    it('refuses to grant a permission the actor does not hold', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);

        expect(fn () => roles()->create($admin, 'Payer', ['billing.manage']))->toThrow(AuthorizationException::class)
            ->and(Role::where('team_id', $company->id)->where('name', 'Payer')->exists())->toBeFalse();
    });

    it('refuses an actor without roles.create', function () {
        expect(fn () => roles()->create(Actors::dispatcher(), 'X', ['trips.view']))->toThrow(AuthorizationException::class);
    });

    it('refuses to edit a role that holds permissions the actor lacks', function () {
        $company = Actors::company();
        $custom = roles()->create($company->owner, 'Finance', ['billing.manage']);
        $admin = Actors::companyAdmin($company);

        expect(fn () => roles()->update($admin, $custom->id, 'Finance', ['billing.view']))->toThrow(AuthorizationException::class)
            ->and(fn () => roles()->delete($admin, $custom->id))->toThrow(AuthorizationException::class);
    });

    it('refuses to edit or delete a locked role', function () {
        $company = Actors::company();
        $owner = $company->owner;

        expect(fn () => roles()->update($owner, roleId($company, 'Owner'), 'Owner', ['trips.view']))->toThrow(AuthorizationException::class)
            ->and(fn () => roles()->delete($owner, roleId($company, 'Driver')))->toThrow(AuthorizationException::class);
    });

    it('returns not found for another company\'s role', function () {
        $a = Actors::company();
        $b = Actors::company();

        expect(fn () => roles()->update($a->owner, roleId($b, 'Dispatcher'), 'Mine', ['trips.view']))->toThrow(ModelNotFoundException::class)
            ->and(fn () => roles()->delete($a->owner, roleId($b, 'Dispatcher')))->toThrow(ModelNotFoundException::class);
    });

    it('keeps the name of a system role', function () {
        $company = Actors::company();

        expect(fn () => roles()->update($company->owner, roleId($company, 'Administrator'), 'Admin', ['trips.view']))
            ->toThrow(AuthorizationException::class)
            ->and(Role::where('team_id', $company->id)->where('name', 'Administrator')->exists())->toBeTrue();
    });

    it('deletes the Administrator role only while unused', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);

        expect(fn () => roles()->delete($company->owner, roleId($company, 'Administrator')))->toThrow(AuthorizationException::class);

        TeamScope::run($company->id, fn () => $admin->removeRole('Administrator'));
        roles()->delete($company->owner, roleId($company, 'Administrator'));

        expect(Role::where('team_id', $company->id)->where('name', 'Administrator')->exists())->toBeFalse();
    });

    it('updates a role and clears the memo of its holders', function () {
        $company = Actors::company();
        $custom = roles()->create($company->owner, 'Yard', ['vehicles.view']);
        $user = Actors::officeUser($company);
        assignments()->assign($company->owner, $user, $custom->id);
        $resolver = app(TenantPermissionResolver::class);
        expect($resolver->has($user, 'trailers.view'))->toBeTrue();

        roles()->update($company->owner, $custom->id, 'Yard', ['vehicles.view', 'reports.view']);

        expect($resolver->has($user, 'reports.view'))->toBeTrue();
    });
});

describe('RoleAssignmentService', function () {
    it('assigns a role, audits it and clears the target\'s memo', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);
        $user = Actors::officeUser($company);
        $resolver = app(TenantPermissionResolver::class);
        expect($resolver->has($user, 'trips.view'))->toBeFalse();

        assignments()->assign($admin, $user, roleId($company, 'Dispatcher'));

        expect($resolver->has($user, 'trips.view'))->toBeTrue()
            ->and(AuditLog::where('action', 'role.assigned')->where('subject_id', $user->id)->where('actor_id', $admin->id)->exists())->toBeTrue();
    });

    it('refuses to assign or remove a role with permissions the actor lacks', function () {
        $company = Actors::company();
        $actor = Actors::dispatcher($company);
        TeamScope::run($company->id, fn () => $actor->givePermissionTo('users.edit'));
        $target = Actors::officeUser($company);
        $admin = Actors::companyAdmin($company);

        expect(fn () => assignments()->assign($actor, $target, roleId($company, 'Administrator')))->toThrow(AuthorizationException::class)
            ->and(fn () => assignments()->remove($actor, $admin, roleId($company, 'Administrator')))->toThrow(AuthorizationException::class)
            ->and($target->fresh()->roles()->where('name', 'Administrator')->exists())->toBeFalse();
    });

    it('refuses self-service', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);

        expect(fn () => assignments()->assign($admin, $admin, roleId($company, 'Dispatcher')))->toThrow(AuthorizationException::class)
            ->and(fn () => assignments()->grantDirect($admin, $admin, 'trips.view'))->toThrow(AuthorizationException::class);
    });

    it('refuses locked roles', function () {
        $company = Actors::company();
        $user = Actors::officeUser($company);

        foreach (['Owner', 'Driver', 'Support (read)', 'Support (read/write)'] as $locked) {
            expect(fn () => assignments()->assign($company->owner, $user, roleId($company, $locked)))->toThrow(AuthorizationException::class);
        }
    });

    it('lets nobody but the Owner change the Owner', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);

        expect(fn () => assignments()->assign($admin, $company->owner, roleId($company, 'Dispatcher')))->toThrow(AuthorizationException::class);
    });

    it('returns not found for another company\'s user or role', function () {
        $a = Actors::company();
        $b = Actors::company();
        $admin = Actors::companyAdmin($a);

        expect(fn () => assignments()->assign($admin, Actors::officeUser($b), roleId($a, 'Dispatcher')))->toThrow(ModelNotFoundException::class)
            ->and(fn () => assignments()->assign($admin, Actors::officeUser($a), roleId($b, 'Dispatcher')))->toThrow(ModelNotFoundException::class);
    });

    it('refuses an actor without users.edit', function () {
        $company = Actors::company();

        expect(fn () => assignments()->assign(Actors::dispatcher($company), Actors::officeUser($company), roleId($company, 'Dispatcher')))
            ->toThrow(AuthorizationException::class);
    });

    it('grants and revokes direct permissions only within what the actor holds', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);
        $user = Actors::officeUser($company);
        $resolver = app(TenantPermissionResolver::class);

        assignments()->grantDirect($admin, $user, 'reports.view');
        expect($resolver->has($user, 'reports.view'))->toBeTrue();

        assignments()->revokeDirect($admin, $user, 'reports.view');
        expect($resolver->has($user, 'reports.view'))->toBeFalse()
            ->and(fn () => assignments()->grantDirect($admin, $user, 'billing.manage'))->toThrow(AuthorizationException::class)
            ->and(fn () => assignments()->grantDirect($admin, $user, 'platform.staff.manage'))->toThrow(AuthorizationException::class);
    });

    it('keeps at least two active Platform Owners', function () {
        $first = Actors::platformOwner();
        $second = Actors::platformOwner();
        $third = Actors::platformOwner();
        $ownerRole = (int) Role::query()->where('team_id', 0)->where('system_key', 'platform_owner')->value('id');

        assignments()->remove($first, $second, $ownerRole);

        expect(fn () => assignments()->remove($first, $third, $ownerRole))->toThrow(AuthorizationException::class)
            ->and(TeamScope::run(0, fn () => $third->fresh()->hasRole('Platform Owner', 'platform')))->toBeTrue();
    });

    it('does not let a platform user manage a tenant user', function () {
        $company = Actors::company();

        expect(fn () => assignments()->assign(Actors::platformOwner(), Actors::officeUser($company), roleId($company, 'Dispatcher')))
            ->toThrow(ModelNotFoundException::class);
    });
});

describe('OwnershipService', function () {
    it('moves the Owner role and the company pointer, and keeps the old owner as Administrator', function () {
        $company = Actors::company();
        $oldOwner = $company->owner;
        $newOwner = Actors::companyAdmin($company);

        app(OwnershipService::class)->transfer($oldOwner, $company, $newOwner->id);

        $resolver = app(TenantPermissionResolver::class);
        expect($company->fresh()->owner_user_id)->toBe($newOwner->id)
            ->and(TeamScope::run($company->id, fn () => $newOwner->fresh()->hasRole('Owner')))->toBeTrue()
            ->and(TeamScope::run($company->id, fn () => $oldOwner->fresh()->hasRole('Owner')))->toBeFalse()
            ->and(TeamScope::run($company->id, fn () => $oldOwner->fresh()->hasRole('Administrator')))->toBeTrue()
            ->and($resolver->has($oldOwner, 'billing.manage'))->toBeFalse()
            ->and($resolver->has($newOwner, 'billing.manage'))->toBeTrue()
            ->and(AuditLog::where('action', 'company.ownership_transferred')->where('subject_id', $company->id)->exists())->toBeTrue();
    });

    it('refuses a non-owner, a foreign user and an inactive user', function () {
        $company = Actors::company();
        $admin = Actors::companyAdmin($company);
        $inactive = Actors::officeUser($company, ['status' => 'inactive']);
        $service = app(OwnershipService::class);

        expect(fn () => $service->transfer($admin, $company, $admin->id))->toThrow(AuthorizationException::class)
            ->and(fn () => $service->transfer($company->owner, $company, Actors::companyAdmin(Actors::company())->id))->toThrow(ModelNotFoundException::class)
            ->and(fn () => $service->transfer($company->owner, $company, $inactive->id))->toThrow(AuthorizationException::class)
            ->and($company->fresh()->owner_user_id)->toBe($company->owner_user_id);
    });

    it('lets platform staff with companies.manage transfer, but not a Support Agent', function () {
        $company = Actors::company();
        $newOwner = Actors::companyAdmin($company);
        $service = app(OwnershipService::class);

        expect(fn () => $service->transfer(Actors::platformSupport(), $company, $newOwner->id))->toThrow(AuthorizationException::class);

        $service->transfer(Actors::platformOwner(), $company, $newOwner->id);

        expect($company->fresh()->owner_user_id)->toBe($newOwner->id);
    });

    it('never lets the support login become owner', function () {
        $company = Actors::company();
        $support = User::where('company_id', $company->id)->where('user_type', 'support')->firstOrFail();
        $support->forceFill(['status' => 'active'])->save();

        expect(fn () => app(OwnershipService::class)->transfer($company->owner, $company, $support->id))->toThrow(ModelNotFoundException::class);
    });
});

describe('legacy user and role screens (replaced in WP4)', function () {
    it('stay closed to tenants, so another company\'s user cannot be changed', function () {
        $a = Actors::company();
        $victim = Actors::officeUser(Actors::company());
        $owner = $a->owner;

        // The 403 page itself still hits a legacy relation (WP5), so assert the refusal directly.
        $this->withoutExceptionHandling()->actingAs($owner);

        expect(fn () => $this->get(route('users.index')))->toThrow(UnauthorizedException::class)
            ->and(fn () => $this->put(route('users.update', $victim->id), [
                'name' => 'Taken', 'email' => $victim->email, 'status' => 'active',
                'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!',
            ]))->toThrow(UnauthorizedException::class);

        expect($victim->fresh()->name)->not->toBe('Taken');
    });

    it('require a permission no tenant user can hold on every user and role route', function () {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => preg_match('/^(users|admin\.roles)\./', (string) $route->getName()));

        expect($routes)->not->toBeEmpty();

        foreach ($routes as $route) {
            expect($route->gatherMiddleware())->toContain('permission:platform.staff.manage');
        }

        expect(Actors::actingAsTenant(Actors::companyOwner())->can('platform.staff.manage'))->toBeFalse();
    });
});

<?php

use App\Authorization\CatalogueRegistrar;
use App\Authorization\PermissionCatalogue;
use App\Authorization\RoleTemplates;
use App\Authorization\TeamScope;
use App\Authorization\TenantPermissionResolver;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Actors;

/*
| 06 §2, §4: permissions are resolved database-first in the user's own team. Spatie's cached
| role data is never used for a decision.
*/

function resolver(): TenantPermissionResolver
{
    return app(TenantPermissionResolver::class);
}

function permissionNamesOf(User $user): array
{
    $names = array_keys(resolver()->names($user));
    sort($names);

    return $names;
}

function sortedTemplate(string $role): array
{
    $names = RoleTemplates::permissionsFor($role);
    sort($names);

    return $names;
}

describe('resolution', function () {
    it('gives the Owner and a Dispatcher exactly their template permissions', function () {
        $company = Actors::company();

        expect(permissionNamesOf($company->owner))->toBe(sortedTemplate('Owner'))
            ->and(permissionNamesOf(Actors::dispatcher($company)))->toBe(sortedTemplate('Dispatcher'));
    });

    it('gives a user nothing in another company team, even with a stray pivot there', function () {
        $a = Actors::company();
        $b = Actors::company();
        $userA = $a->owner;
        $ownerRoleB = Role::query()->where('team_id', $b->id)->where('system_key', 'owner')->value('id');

        // An application bug records user A under team B.
        DB::table('model_has_roles')->insert([
            'team_id' => $b->id, 'role_id' => $ownerRoleB, 'model_type' => $userA->getMorphClass(), 'model_id' => $userA->id,
        ]);

        expect(resolver()->names($userA))->toHaveCount(count(PermissionCatalogue::TENANT));

        $foreign = DB::selectOne('SELECT COUNT(*) AS n FROM model_has_roles m JOIN users u ON u.id = m.model_id AND m.model_type = ? WHERE m.team_id <> u.company_id', [$userA->getMorphClass()]);
        expect((int) $foreign->n)->toBe(1);
    });

    it('rejects a pivot that points at another team\'s role', function () {
        $a = Actors::company();
        $b = Actors::company();
        $ownerRoleB = Role::query()->where('team_id', $b->id)->where('system_key', 'owner')->value('id');

        expect(fn () => DB::table('model_has_roles')->insert([
            'team_id' => $a->id, 'role_id' => $ownerRoleB, 'model_type' => (new User)->getMorphClass(), 'model_id' => $a->owner_user_id,
        ]))->toThrow(QueryException::class);
    });

    it('keeps same-named roles in two companies independent', function () {
        $a = Actors::company();
        $b = Actors::company();
        $userA = Actors::dispatcher($a);
        $userB = Actors::dispatcher($b);

        TeamScope::run($a->id, fn () => Role::findByName('Dispatcher', 'web')->syncPermissions(['drivers.view']));

        expect(permissionNamesOf($userA))->toBe(['drivers.view'])
            ->and(permissionNamesOf($userB))->toBe(sortedTemplate('Dispatcher'));
    });

    it('hides another company\'s role from Spatie lookups', function () {
        $a = Actors::company();
        $b = Actors::company();
        $roleB = Role::query()->where('team_id', $b->id)->where('name', 'Dispatcher')->value('id');

        expect(fn () => TeamScope::run($a->id, fn () => Role::findById($roleB, 'web')))->toThrow(RoleDoesNotExist::class);
    });

    it('counts direct grants in the user\'s own team', function () {
        $company = Actors::company();
        $user = Actors::dispatcher($company);

        TeamScope::run($company->id, fn () => $user->givePermissionTo('reports.view'));
        resolver()->forget($user);

        expect(resolver()->has($user, 'reports.view'))->toBeTrue();
    });

    it('memoises per request and forgets on demand', function () {
        $company = Actors::company();
        $user = Actors::dispatcher($company);

        expect(resolver()->has($user, 'drivers.view'))->toBeTrue();

        TeamScope::run($company->id, fn () => $user->removeRole('Dispatcher'));
        expect(resolver()->has($user, 'drivers.view'))->toBeTrue();

        resolver()->forget($user);
        expect(resolver()->has($user, 'drivers.view'))->toBeFalse();
    });

    it('resolves platform staff in team 0 with the platform guard only', function () {
        $owner = Actors::platformOwner();
        $support = Actors::platformSupport();

        expect(resolver()->has($owner, 'platform.staff.manage'))->toBeTrue()
            ->and(resolver()->has($support, 'platform.staff.manage'))->toBeFalse()
            ->and(resolver()->has($support, 'platform.support.request'))->toBeTrue()
            ->and($owner->can('platform.staff.manage'))->toBeTrue()
            ->and($support->can('platform.staff.manage'))->toBeFalse();
    });
});

describe('every Spatie entry point goes through the resolver', function () {
    it('answers hasPermissionTo, hasAnyPermission, hasAllPermissions and checkPermissionTo', function () {
        $user = Actors::actingAsTenant(Actors::dispatcher());

        expect($user->hasPermissionTo('trips.edit'))->toBeTrue()
            ->and($user->hasPermissionTo('billing.manage'))->toBeFalse()
            ->and($user->hasAnyPermission('billing.manage', 'trips.view'))->toBeTrue()
            ->and($user->hasAllPermissions('trips.view', 'billing.manage'))->toBeFalse()
            ->and($user->checkPermissionTo('no.such.permission'))->toBeFalse()
            ->and(fn () => $user->hasPermissionTo('no.such.permission'))->toThrow(PermissionDoesNotExist::class)
            ->and(fn () => $user->hasPermissionTo('platform.staff.manage'))->toThrow(PermissionDoesNotExist::class)
            ->and($user->checkPermissionTo('platform.staff.manage'))->toBeFalse();
    });

    it('answers can() and @can', function () {
        $user = Actors::actingAsTenant(Actors::dispatcher());

        expect($user->can('trips.view'))->toBeTrue()
            ->and($user->can('billing.manage'))->toBeFalse()
            ->and(trim(Blade::render("@can('trips.view') yes @else no @endcan")))->toBe('yes')
            ->and(trim(Blade::render("@can('billing.manage') yes @else no @endcan")))->toBe('no');
    });

    it('answers the permission: middleware', function () {
        Route::middleware(['web', 'auth', 'permission:trips.view'])->get('/_test/trips', fn () => 'ok');
        Route::middleware(['web', 'auth', 'permission:billing.manage'])->get('/_test/billing', fn () => 'ok');
        $user = Actors::dispatcher();

        $this->actingAs($user)->get('/_test/trips')->assertOk();
        $this->actingAs($user)->get('/_test/billing')->assertForbidden();
    });
});

describe('Gate::before', function () {
    it('denies a tenant user whose context is another company', function () {
        $user = Actors::dispatcher();
        $other = Actors::company();
        Actors::actingAsTenant($user);
        app(TenantContext::class)->set($other->id);

        expect($user->can('trips.view'))->toBeFalse();
    });

    it('denies a tenant user with no tenant context', function () {
        $user = Actors::dispatcher();

        expect($user->can('trips.view'))->toBeFalse();
    });

    it('denies a tenant user a platform permission', function () {
        $user = Actors::actingAsTenant(Actors::companyOwner());

        expect($user->can('platform.settings.manage'))->toBeFalse();
    });

    it('denies platform staff inside a tenant context', function () {
        $owner = Actors::platformOwner();
        app(TenantContext::class)->set(Actors::company()->id);

        expect($owner->can('platform.staff.manage'))->toBeFalse();
    });

    it('leaves abilities outside the catalogue to policies', function () {
        $user = Actors::actingAsTenant(Actors::dispatcher());
        Gate::define('custom-ability', fn () => true);

        expect($user->can('custom-ability'))->toBeTrue()
            ->and($user->can('undefined-ability'))->toBeFalse();
    });
});

describe('architecture', function () {
    it('binds the catalogue registrar and caches permissions without roles', function () {
        Actors::company();
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        expect($registrar)->toBeInstanceOf(CatalogueRegistrar::class)
            ->and($registrar->getPermissions()->count())->toBe(Permission::count())
            ->and($registrar->getPermissions()->sum(fn ($p) => $p->roles->count()))->toBe(0)
            ->and(config('permission.register_permission_check_method'))->toBeFalse()
            ->and(config('permission.models.permission'))->toBe(Permission::class)
            ->and(config('permission.models.role'))->toBe(Role::class);
    });

    it('never reads a permission\'s roles for a decision', function () {
        $offenders = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getRealPath() === (new ReflectionClass(CatalogueRegistrar::class))->getFileName()) {
                continue;
            }

            foreach (file($file->getRealPath()) as $number => $line) {
                // Spatie's permission()/withoutPermission() scopes read $permission->roles from the cache too.
                if (preg_match('/\$\w*permission\w*->roles\b|getPermissionsWithRoles|hasPermissionViaRole|(::|->)(permission|withoutPermission)\(/i', $line)) {
                    $offenders[] = $file->getRelativePathname().':'.($number + 1);
                }
            }
        }

        expect($offenders)->toBe([]);
    });
});

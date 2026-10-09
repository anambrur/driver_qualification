<?php

use App\Models\Company;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\Actors;

/*
| USR-04: PermissionSeeder used to truncate users + companies and create superadmin@gmail.com / 12345678.
| Decision (2026-10-09): PermissionSeeder is a non-destructive sync that is safe in production;
| the demo super-admin lives in DemoUserSeeder, which only runs locally.
| The test DB is in-memory SQLite (phpunit.xml).
*/

function runSeederIn(string $environment, string $seeder): void
{
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');

    app()->detectEnvironment(fn () => $environment);

    try {
        (new $seeder)->run();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }
}

describe('USR-04: PermissionSeeder is a non-destructive sync', function () {
    it('keeps existing users and companies and creates no demo login, even in production', function () {
        $owner = Actors::companyOwner();
        $usersBefore = User::count();
        $companiesBefore = Company::count();

        runSeederIn('production', PermissionSeeder::class);

        expect(User::count())->toBe($usersBefore)
            ->and(Company::count())->toBe($companiesBefore)
            ->and($owner->fresh()->hasRole('company'))->toBeTrue()
            ->and(User::query()->where('email', 'superadmin@gmail.com')->exists())->toBeFalse();
    });

    it('creates every permission and the default role mapping on an empty database', function () {
        runSeederIn('production', PermissionSeeder::class);

        $companyExpected = Permission::query()
            ->where(function ($query) {
                foreach (PermissionSeeder::COMPANY_MODULES as $module) {
                    $query->orWhere('name', 'like', $module.'.%');
                }
            })
            ->pluck('name')
            ->merge(PermissionSeeder::COMPANY_EXTRA_PERMISSIONS)
            ->sort()->values()->all();

        expect(Permission::pluck('name')->sort()->values()->all())
            ->toBe(collect(PermissionSeeder::permissionNames())->sort()->values()->all())
            ->and(Role::findByName('super-admin', 'web')->permissions()->count())->toBe(Permission::count())
            ->and(Role::findByName('company', 'web')->permissions->pluck('name')->sort()->values()->all())->toBe($companyExpected)
            ->and(Role::query()->where('name', 'user')->exists())->toBeTrue();
    });

    it('is idempotent and adds a permission that is new in the code', function () {
        runSeederIn('production', PermissionSeeder::class);
        Permission::query()->where('name', 'drivers.hire')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        runSeederIn('production', PermissionSeeder::class);
        runSeederIn('production', PermissionSeeder::class);

        expect(Permission::count())->toBe(count(PermissionSeeder::permissionNames()))
            ->and(Role::query()->count())->toBe(3)
            ->and(Role::findByName('super-admin', 'web')->hasPermissionTo('drivers.hire'))->toBeTrue()
            ->and(Role::findByName('company', 'web')->hasPermissionTo('drivers.hire'))->toBeTrue();
    });

    it('keeps extra permissions an admin gave the company role in the Roles UI', function () {
        runSeederIn('production', PermissionSeeder::class);
        Role::findByName('company', 'web')->givePermissionTo('asset-groups.view');

        runSeederIn('production', PermissionSeeder::class);

        expect(Role::findByName('company', 'web')->hasPermissionTo('asset-groups.view'))->toBeTrue();
    });

    it('gives back every permission to a super-admin role that lost some', function () {
        runSeederIn('production', PermissionSeeder::class);
        Role::findByName('super-admin', 'web')->revokePermissionTo('roles.edit');

        runSeederIn('production', PermissionSeeder::class);

        expect(Role::findByName('super-admin', 'web')->permissions()->count())->toBe(Permission::count());
    });
});

describe('USR-04: DemoUserSeeder (demo super-admin) is local only', function () {
    it('refuses to run outside local and testing', function (string $environment) {
        Actors::seedAccessControl();
        $usersBefore = User::count();

        expect(fn () => runSeederIn($environment, DemoUserSeeder::class))
            ->toThrow(RuntimeException::class, 'DemoUserSeeder');

        expect(User::count())->toBe($usersBefore)
            ->and(User::query()->where('email', 'superadmin@gmail.com')->exists())->toBeFalse();
    })->with(['production', 'staging']);

    it('creates the demo super-admin and company locally, once', function () {
        Actors::seedAccessControl();
        $tenant = Actors::companyOwner();

        runSeederIn('local', DemoUserSeeder::class);
        runSeederIn('local', DemoUserSeeder::class);

        $demo = User::query()->where('email', 'superadmin@gmail.com')->sole();
        expect($demo->hasRole('super-admin'))->toBeTrue()
            ->and(Hash::check('12345678', $demo->password))->toBeTrue()
            ->and(Company::query()->where('user_id', $demo->id)->count())->toBe(1)
            ->and($tenant->fresh())->not->toBeNull();
    });
});

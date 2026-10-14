<?php

use App\Authorization\PermissionCatalogue;
use App\Authorization\RoleTemplates;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Actors;

/*
| PermissionSeeder syncs the catalogue and is safe in production: it never deletes a permission,
| never touches company roles or their grants, and creates no legacy roles (06 §10).
| PlatformRoleSeeder owns the platform system roles in team 0.
*/

it('creates exactly the catalogue with its flags', function () {
    expect(Permission::where('guard_name', 'web')->pluck('name')->sort()->values()->all())
        ->toBe(collect(PermissionCatalogue::tenantNames())->sort()->values()->all())
        ->and(Permission::where('guard_name', 'platform')->count())->toBe(count(PermissionCatalogue::PLATFORM))
        ->and(Permission::where('name', 'trips.drive.own')->first())
        ->is_write->toBeTrue()->self_scoped->toBeTrue()
        ->and(Permission::where('name', 'drivers.view')->first())
        ->is_write->toBeFalse()->self_scoped->toBeFalse();
});

it('is idempotent and never touches company roles or grants, even in production', function () {
    $company = Actors::company();
    $dispatcher = Role::where('team_id', $company->id)->where('name', 'Dispatcher')->firstOrFail();
    $dispatcher->syncPermissions(['drivers.view']);
    $before = [User::count(), Company::count(), Role::count(), DB::table('role_has_permissions')->count(), Permission::count()];

    app()->detectEnvironment(fn () => 'production');

    try {
        (new PermissionSeeder)->run();
        (new PermissionSeeder)->run();
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect([User::count(), Company::count(), Role::count(), DB::table('role_has_permissions')->count(), Permission::count()])->toBe($before)
        ->and($dispatcher->fresh()->permissions()->pluck('name')->all())->toBe(['drivers.view']);
});

it('leaves permissions outside the catalogue in place', function () {
    Permission::create(['name' => 'legacy.thing', 'guard_name' => 'web']);

    (new PermissionSeeder)->run();

    expect(Permission::where('name', 'legacy.thing')->exists())->toBeTrue();
});

it('sets feature_key once the feature row exists', function () {
    expect(Permission::where('name', 'trips.view')->value('feature_key'))->toBeNull();

    DB::table('features')->insert(['key' => 'trips', 'name' => 'Trips', 'group' => 'Operations', 'kind' => 'module', 'created_at' => now(), 'updated_at' => now()]);
    (new PermissionSeeder)->run();

    expect(Permission::where('name', 'trips.view')->value('feature_key'))->toBe('trips')
        ->and(Permission::where('name', 'company.edit')->value('feature_key'))->toBeNull();
});

it('creates no legacy or null-team roles', function () {
    (new PermissionSeeder)->run();
    (new PlatformRoleSeeder)->run();

    expect(Role::whereIn('name', ['super-admin', 'company', 'user'])->exists())->toBeFalse()
        ->and(DB::table('roles')->whereNull('team_id')->exists())->toBeFalse();
});

it('seeds the platform roles in team 0, idempotently, with the 04 §3 grants', function () {
    (new PlatformRoleSeeder)->run();
    (new PlatformRoleSeeder)->run();

    $roles = Role::where('team_id', 0)->get();

    expect($roles)->toHaveCount(count(RoleTemplates::PLATFORM_ROLES))
        ->and($roles->every(fn (Role $role) => $role->guard_name === 'platform' && $role->is_system))->toBeTrue()
        ->and($roles->firstWhere('system_key', 'platform_owner')->permissions()->count())->toBe(count(PermissionCatalogue::PLATFORM))
        ->and($roles->firstWhere('system_key', 'platform_support')->permissions()->pluck('name')->sort()->values()->all())
        ->toBe(['platform.companies.view', 'platform.subscriptions.view', 'platform.support.request']);
});

it('refuses to run DemoSeeder, which creates known-password logins, in production', function () {
    app()->detectEnvironment(fn () => 'production');

    try {
        expect(fn () => (new DemoSeeder)->run())->toThrow(RuntimeException::class);
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    expect(DB::table('platform_users')->count())->toBe(0);
});

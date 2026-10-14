<?php

use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\Support\Actors;

/*
| ACL-09: super-admin is detected by role *name* everywhere (`role:super-admin` middleware,
| hasRole('super-admin') in Subscribed and CompanyFilterTrait). That is only safe while the name
| can't change. USR-03 made super-admin and company system roles that can't be renamed or
| deleted; these tests prove access control survives an attempt to do either.
*/

// Only the legacy `role:super-admin` remains until the platform area replaces it (06 §10, WP8);
// tenant routes use `permission:` checks, never role names.
it('uses no role name in route middleware except the legacy super-admin', function () {
    $roles = collect(Route::getRoutes()->getRoutes())
        ->flatMap(fn ($route) => $route->gatherMiddleware())
        ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'role:'))
        ->flatMap(fn (string $m) => explode('|', substr($m, 5)))
        ->unique()->values()->all();

    expect($roles)->not->toBeEmpty()
        ->and(array_diff($roles, ['super-admin']))->toBe([]);
});

it('keeps super-admin access after an attempt to rename or delete the role', function () {
    $admin = Actors::superAdmin();
    $role = Role::findByName('super-admin', 'web');

    $this->actingAs($admin)->put(route('admin.roles.update', $role->id), ['name' => 'root']);
    $this->actingAs($admin)->delete(route('admin.roles.destroy', $role->id));

    expect(Role::where('name', 'super-admin')->exists())->toBeTrue()
        ->and(Role::where('name', 'root')->exists())->toBeFalse();

    // role:super-admin routes and the Subscribed bypass (the admin has no subscription).
    $this->actingAs($admin->fresh())->get(route('admin.plans.index'))->assertOk();
    $this->actingAs($admin->fresh())->get(route('admin.dashboard'))->assertOk();
});

it('keeps tenants out of super-admin routes after an attempt to rename the company role', function () {
    $admin = Actors::superAdmin();
    $tenant = Actors::companyOwner();
    $company = Role::findByName('company', 'web');

    $this->actingAs($admin)->put(route('admin.roles.update', $company->id), ['name' => 'super-admin-2']);

    expect($company->fresh()->name)->toBe('company');
    $this->actingAs($tenant->fresh())->get(route('admin.plans.index'))->assertForbidden();
});

<?php

use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Actors;

/*
| ACL-09, after WP8 (11 §5): the legacy super-admin role is gone (06 §10). No route checks a
| role *name*, so naming a role "super-admin" grants nothing; platform staff work only in
| /platform and can't reach tenant routes.
*/

it('uses no role name in route middleware', function () {
    $roles = collect(Route::getRoutes()->getRoutes())
        ->flatMap(fn ($route) => $route->gatherMiddleware())
        ->filter(fn ($m) => is_string($m) && str_starts_with($m, 'role:'))
        ->values()->all();

    expect($roles)->toBe([]);
});

it('grants nothing to a company role named super-admin', function () {
    $user = Actors::plainUser();
    $role = Role::query()->create(['name' => 'super-admin', 'guard_name' => 'web', 'team_id' => $user->company_id]);
    app(PermissionRegistrar::class)->setPermissionsTeamId($user->company_id);
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.driver.index'))->assertForbidden();
    $this->actingAs($user)->get(route('platform.companies.index'))->assertRedirect(route('platform.login'));
});

it('keeps platform staff out of tenant routes', function () {
    $this->actingAs(Actors::platformOwner(), 'platform');
    // actingAs() also made `platform` the default guard; a real request starts on `web`.
    Auth::shouldUse('web');

    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

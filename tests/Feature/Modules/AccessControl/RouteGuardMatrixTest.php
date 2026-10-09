<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Tests\Support\Actors;

/*
| Route guard matrix. Every registered route must fall into one of the buckets below, so a new
| route that forgets its middleware fails here instead of shipping open:
|   - public (no `auth`): listed in ACL_PUBLIC_ROUTES with the reason it is safe;
|   - every /admin route has `Subscribed` or `role:super-admin`;
|   - every authenticated route has `permission:` / `role:`, or is listed in ACL_NO_PERMISSION_ROUTES.
| Routes are keyed "METHODS uri" (HEAD dropped), because several routes have no name.
*/

const ACL_PUBLIC_ROUTES = [
    // Landing page and health check.
    'GET /',
    'GET up',
    // Laravel's local-disk file routes: they only serve signed temporary URLs.
    'GET storage/{path}',
    'PUT storage/{path}',
    // Verified by the Stripe signature header.
    'POST stripe/webhook',
    // Breeze guest routes.
    'GET register',
    'POST register',
    'GET login',
    'POST login',
    'GET forgot-password',
    'POST forgot-password',
    'GET reset-password/{token}',
    'POST reset-password',
];

// Public driver application: OTP-gated session (see PublicApplication module).
const ACL_PUBLIC_PREFIXES = ['{slug}/apply', '{slug}/application/'];

const ACL_GUEST_ROUTES = [
    'GET register', 'POST register', 'GET login', 'POST login',
    'GET forgot-password', 'POST forgot-password', 'GET reset-password/{token}', 'POST reset-password',
];

const ACL_NO_PERMISSION_ROUTES = [
    // Every logged-in user: landing page, own account, email verification, logout.
    'GET dashboard',
    'GET admin/dashboard',
    'GET admin/profile',
    'PATCH admin/profile',
    'DELETE admin/profile',
    'GET verify-email',
    'GET verify-email/{id}/{hash}',
    'POST email/verification-notification',
    'GET confirm-password',
    'POST confirm-password',
    'PUT password',
    'POST logout',
    // Billing must be reachable without a subscription or permission, to buy one.
    'GET billing',
    'GET billing/portal',
    'POST billing/cancel',
    'POST billing/resume',
    'GET pricing/plans',
    'GET checkout-success',
    'GET checkout/{name}',
    'GET subscription/plans',
    'GET subscription/my',
    'GET subscription/renew',
    'GET subscription/expired',
    'GET subscription/checkout/{plan}',
    // Global lookups: read-only for every tenant, writes need super-admin permissions (LKP-02).
    'GET admin/vehicle-type',
    'GET admin/vehicle-group',
    'GET admin/fuel-type',
    'GET admin/equipment-type',
    'GET admin/maintenance-category',
    'GET admin/settings/document-types',
    'GET admin/settings/document-types/by-module',
    'GET admin/settings/document-types/{id}',
];

/**
 * @return Collection<int, array{key: string, uri: string, name: ?string, middleware: list<string>}>
 */
function aclRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())->map(fn (RoutingRoute $route) => [
        'key' => implode('|', array_values(array_diff($route->methods(), ['HEAD']))).' '.$route->uri(),
        'uri' => $route->uri(),
        'name' => $route->getName(),
        'middleware' => $route->gatherMiddleware(),
    ]);
}

function aclIsPublic(array $route): bool
{
    return in_array($route['key'], ACL_PUBLIC_ROUTES, true)
        || collect(ACL_PUBLIC_PREFIXES)->contains(fn (string $prefix) => str_starts_with($route['uri'], $prefix));
}

function aclHasMiddleware(array $route, string $prefix): bool
{
    return collect($route['middleware'])->contains(fn ($m) => is_string($m) && ($m === $prefix || str_starts_with($m, $prefix.':')));
}

describe('route guard matrix', function () {
    it('requires auth on every route that is not explicitly public', function () {
        $open = aclRoutes()
            ->reject(fn (array $route) => aclIsPublic($route))
            ->reject(fn (array $route) => aclHasMiddleware($route, 'auth'))
            ->pluck('key')->values()->all();

        expect($open)->toBe([]);
    });

    it('only lists public routes that still exist', function () {
        $keys = aclRoutes()->pluck('key')->all();

        expect(array_values(array_diff(ACL_PUBLIC_ROUTES, $keys)))->toBe([]);
    });

    it('keeps the login, register and password reset routes guest-only', function () {
        $routes = aclRoutes()->keyBy('key');

        foreach (ACL_GUEST_ROUTES as $key) {
            expect(aclHasMiddleware($routes[$key], 'guest'))->toBeTrue("$key is missing the guest middleware");
        }
    });

    it('puts every /admin route behind Subscribed or role:super-admin', function () {
        $unguarded = aclRoutes()
            ->filter(fn (array $route) => str_starts_with($route['uri'], 'admin/'))
            ->reject(fn (array $route) => aclHasMiddleware($route, 'Subscribed')
                || in_array('role:super-admin', $route['middleware'], true))
            ->pluck('key')->values()->all();

        expect($unguarded)->toBe([]);
    });

    it('lists exactly the authenticated routes that have no permission or role middleware', function () {
        $withoutPermission = aclRoutes()
            ->reject(fn (array $route) => aclIsPublic($route))
            ->reject(fn (array $route) => aclHasMiddleware($route, 'permission') || aclHasMiddleware($route, 'role'))
            ->pluck('key')->sort()->values()->all();

        expect($withoutPermission)->toBe(collect(ACL_NO_PERMISSION_ROUTES)->sort()->values()->all());
    });
});

describe('ACL-01 → maintenance routes need maintenance/scheduled permissions', function () {
    it('guards each route with the permission the sidebar uses', function (string $name, string $permission) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain("permission:$permission");
    })->with([
        ['admin.service-log.index', 'maintenance.view'],
        ['admin.service-log.dropdown-data', 'maintenance.view'],
        ['admin.service-log.show', 'maintenance.view'],
        ['admin.service-log.get-vehicle-details', 'maintenance.view'],
        ['admin.service-log.download-document', 'maintenance.view'],
        ['admin.service-log.store', 'maintenance.create'],
        ['admin.service-log.edit', 'maintenance.edit'],
        ['admin.service-log.update', 'maintenance.edit'],
        ['admin.service-log.delete-document', 'maintenance.edit'],
        ['admin.service-log.destroy', 'maintenance.delete'],
        ['admin.maintenance-schedule.index', 'scheduled.view'],
        ['admin.maintenance-schedule.dropdown-data', 'scheduled.view'],
        ['admin.maintenance-schedule.show', 'scheduled.view'],
        ['admin.maintenance-schedule.get-vehicle-details', 'scheduled.view'],
        ['admin.maintenance-schedule.store', 'scheduled.create'],
        ['admin.maintenance-schedule.edit', 'scheduled.edit'],
        ['admin.maintenance-schedule.update', 'scheduled.edit'],
        ['admin.maintenance-schedule.mark-completed', 'scheduled.edit'],
        ['admin.maintenance-schedule.destroy', 'scheduled.delete'],
    ]);

    it('forbids a subscribed user without those permissions', function (string $method, string $uri) {
        $this->actingAs(Actors::plainUser())
            ->json($method, $uri)
            ->assertForbidden();
    })->with([
        ['GET', 'admin/service-log'],
        ['POST', 'admin/service-log'],
        ['GET', 'admin/service-log/dropdown-data'],
        ['GET', 'admin/service-log/1'],
        ['GET', 'admin/service-log/1/edit'],
        ['PUT', 'admin/service-log/1'],
        ['DELETE', 'admin/service-log/1'],
        ['GET', 'admin/service-log/vehicle/1/details'],
        ['GET', 'admin/service-log/document/1/download'],
        ['DELETE', 'admin/service-log/document/1'],
        ['GET', 'admin/maintenance-schedule'],
        ['POST', 'admin/maintenance-schedule'],
        ['GET', 'admin/maintenance-schedule/dropdown-data'],
        ['GET', 'admin/maintenance-schedule/1'],
        ['GET', 'admin/maintenance-schedule/1/edit'],
        ['PUT', 'admin/maintenance-schedule/1'],
        ['DELETE', 'admin/maintenance-schedule/1'],
        ['GET', 'admin/maintenance-schedule/vehicle/1/details'],
        ['POST', 'admin/maintenance-schedule/1/mark-completed'],
    ]);

    it('still lets a tenant open service logs and maintenance schedules', function (string $name) {
        $this->actingAs(Actors::companyOwner())
            ->get(route($name))
            ->assertOk();
    })->with(['admin.service-log.index', 'admin.maintenance-schedule.index']);
});

// The page was an unrelated investment calculator; the product owner had it removed (2026-10-09).
describe('ACL-04 → /profit is removed', function () {
    it('returns 404 for everyone', function (?Closure $makeUser) {
        if ($makeUser) {
            $this->actingAs($makeUser());
        }

        $this->get('/profit')->assertNotFound();
    })->with([
        'guest' => [null],
        'tenant' => [fn () => Actors::companyOwner()],
        'super-admin' => [fn () => Actors::superAdmin()],
    ]);

    it('no longer registers the admin.profit route', function () {
        expect(Route::has('admin.profit'))->toBeFalse();
    });
});

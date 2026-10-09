# Module report: `access-control` (cross-cutting: route guards, tenancy trait, deps, config)

Run date: 2026-10-09, with a follow-up the same day after the product owner's answers. Tests: `tests/Feature/Modules/AccessControl` and `tests/Unit/Modules/AccessControl` (82 tests, all passing; the `composer audit` test needs network access).
Full suite: 483 passed, 10 failed. The 10 failures match the baseline (`Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4, `ProfileTest` ×5), so nothing regressed.
Larastan on the scope files and the touched controllers found no new errors. All 30 reported errors were already there: the `ServiceLog`, `MaintenanceSchedule` and three document models have no relation return types. Those belong to the maintenance, dashboard and compliance modules.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| ACL-01 | P1 | FIXED | `RouteGuardMatrixTest.php::ACL-01 → it guards each route with the permission the sidebar uses` (×19); `…::it forbids a subscribed user without those permissions` (×19); regression guard `…::it still lets a tenant open service logs and maintenance schedules` (×2); matrix `…::route guard matrix → it lists exactly the authenticated routes that have no permission or role middleware` | `routes/web.php` |
| ACL-02 | P1 | FIXED | `ComposerAuditTest.php::it has no security advisories for the locked dependencies` (group `audit`) | `composer.json`, `composer.lock`, `tests/Feature/Modules/UsersRoles/{RoleErrorMessageTest,RoleManagementTest}.php` |
| ACL-03 | P1 | FIXED | `PublicRouteThrottleTest.php::it rate limits every POST a guest can send`; `…::it returns 429 after too many attempts from one client` (register, forgot-password, reset-password) | `routes/auth.php` |
| ACL-04 | P2 | FIXED (removed) | `RouteGuardMatrixTest.php::ACL-04 → /profit is removed → it returns 404 for everyone` (guest, tenant, super-admin); `…::it no longer registers the admin.profit route`; matrix `…::route guard matrix → it requires auth on every route that is not explicitly public` | `routes/web.php`, `DashboardController.php`, `resources/views/admin/profit.blade.php` (deleted) |
| ACL-05 | P2 | FIXED | `SuperAdminCompanyAttributionTest.php::it files a super-admin's service log under the vehicle's company`; `…::it files a super-admin's maintenance schedule under the vehicle's company`; regression guards `…::it keeps the super-admin's own company for a schedule with no vehicle`, `…::it still files a tenant's records under the tenant's company`; `CompanyFilterTraitTest.php::super-admin → it files new records under the resource's company, or its own when there is none`; `…::tenant → it owns new records itself, whatever company the resource belongs to` | `app/Traits/CompanyFilterTrait.php`, `ServiceLogController.php`, `MaintenanceScheduleController.php` |
| ACL-06 | P2 | FIXED | `HtaccessTest.php::it never redirects visitors to a loopback address`; regression guards `…::it still rewrites to /public, forces HTTPS and keeps the cPanel PHP handler`, `…::it defaults APP_DEBUG to off when the variable is missing` | `.htaccess` |
| ACL-07 | P3 | FIXED | `ArchitectureTest.php::ACL-07 → it registers every middleware class in app/Http/Middleware`; `…::it only imports classes that exist in bootstrap/app.php`; `…::it uses every trait in app/Traits` | `bootstrap/app.php`, `app/Http/Middleware/BlockExpiredLogin.php` (deleted), `app/Traits/HasSubscription.php` (deleted) |
| ACL-08 | P3 | FIXED | `ArchitectureTest.php::ACL-08 → env() outside config/ → it is not called from Blade views or route files`; `arch env() is only read in config files`; `AnalyticsFallbackConfigTest.php::it falls back to the configured GA id when site settings have none` (welcome, main layout) | `config/app.php`, `resources/views/welcome.blade.php`, `resources/views/layouts/main-layout.blade.php`, `.env.example` |
| ACL-09 | P2 | DISMISSED | `SuperAdminRoleNameTest.php::it only uses role names in route middleware that are protected system roles`; `…::it keeps super-admin access after an attempt to rename or delete the role`; `…::it keeps tenants out of super-admin routes after an attempt to rename the company role` | none |

Deliverables from the module section:

- **Route guard matrix:** `RouteGuardMatrixTest.php::route guard matrix`. It walks `Route::getRoutes()` and checks five things:
  - Every route that is not explicitly public has `auth`.
  - The public allowlist has no stale entries.
  - The guest auth routes have `guest`.
  - Every `admin/*` route has `Subscribed` or `role:super-admin`.
  - The set of authenticated routes without `permission:`/`role:` exactly matches `ACL_NO_PERMISSION_ROUTES`, which gives a reason for each. A new route without a guard fails the test until someone puts it in a list on purpose.
- **`CompanyFilterTrait` unit tests:** `tests/Unit/Modules/AccessControl/CompanyFilterTraitTest.php` (10 tests). They cover a tenant, a super-admin, a user with no company and a guest. Each is checked against filtering, `userHasAccess`, `authorizeCompanyAccess` (403), `getCompaniesForUser`, `getAllUserCompanyId` and the new `getOwningCompanyId`.
- **`composer audit`:** `ComposerAuditTest.php` (see ACL-02).
- **Arch tests:** `ArchitectureTest.php` checks:
  - `arch()->expect('App')->not->toUse(['dd','dump','ray','var_dump'])`
  - `arch()->expect('App')->not->toUse('env')`
  - a file scan for `env(` in `resources/views` and `routes`
  - the ACL-07 dead-code checks

No factories were added. The tests use the existing `Vehicle`/`Company` factories, and they create `MaintenanceCategory` rows inline.

### What changed

- **ACL-01: maintenance permissions.**
  - All 10 `admin.service-log.*` routes now need `permission:maintenance.<view|create|edit|delete>`, and all 9 `admin.maintenance-schedule.*` routes need `permission:scheduled.<…>`.
  - The mapping: index, show, dropdown, vehicle details and document download → `view`; store → `create`; edit, update, delete-document and mark-completed → `edit`; destroy → `delete`.
  - These are the permissions the sidebar already checks (`@can('maintenance.view')`, `@can('scheduled.view')`), and the `company` role already has them. Tenants see no change.
  - The other items listed in the hotspot were already fixed by earlier modules: the lookups (LKP-02), asset-group (AGR-01), settings/site (SET-01) and `/users` (USR-01). The matrix now checks all of them.
  - The lookup `index`/`show`/`by-module` routes deliberately stay without `permission:`, because tenants keep read-only access (LKP-02 decision). They are listed in `ACL_NO_PERMISSION_ROUTES`.
- **ACL-02: dependencies.** The product owner approved the update on 2026-10-09.
  - Ran `composer update` with no constraint changes. 87 packages changed. Main ones:
    - `laravel/framework` 12.52.0 → 12.69.3
    - `guzzlehttp/guzzle` 7.10.0 → 7.15.5
    - `guzzlehttp/psr7` 2.8.0 → 2.13.1
    - `league/commonmark` 2.8.0 → 2.10.3
    - `dompdf/dompdf` 3.1.4 → 3.1.6
    - `symfony/http-kernel` 7.4.5 → 7.4.20
    - `symfony/mime` 7.4.5 → 7.4.19
    - `php-flasher/*` 2.4.0 → 2.6.3
    - `spatie/laravel-permission` 6.24.1 → 6.25.0
  - Major-version jumps happened only in dev/test tooling: `hamcrest/hamcrest-php` 2 → 3 (via mockery), `phpdocumentor/reflection-docblock` 5 → 6 and `phpdocumentor/type-resolver` 1 → 2. `stripe/stripe-php` is unchanged (16.6.0).
  - Then ran `composer remove yoeunes/toastr`. It is abandoned and nothing referenced it: the app's `toastr()` comes from `php-flasher/flasher-toastr`. This also dropped its dependencies, `yoeunes/regex-parser` and `thecodingmachine/safe`.
  - `composer audit` now reports **no advisories**.
  - php-flasher 2.6 stores each session envelope **serialized**. The app is unaffected, because flasher reads its own bag. Two UsersRoles test helpers read `session('flasher::envelopes')` directly, so they now unserialize string entries.
  - The audit test moved from `known-issue` to an `audit` group that runs by default. It is skipped when offline.
- **ACL-03: throttles.** The OTP routes were throttled in APP-04. This run adds `throttle:6,1` (the same as Breeze's verification routes) to `POST register`, `POST forgot-password` and `POST reset-password`. Three public POSTs are exempt, and the test lists each with its reason:
  - `POST login` has the `LoginRequest` limiter (5 attempts per email + IP).
  - `POST stripe/webhook` is verified by its signature.
  - The application step POSTs and `withdraw` need an OTP-verified application session (APP-01).
- **ACL-04: `/profit` removed.** The product owner decided on 2026-10-09 to remove it completely. The route, `DashboardController::profit()` (and its now-unused `Request` import) and `resources/views/admin/profit.blade.php` are deleted. Nothing linked to it. `GET /profit` now returns 404. This also settles **DSH-01** in the dashboard module.
- **ACL-05: who owns a super-admin's record.**
  - New `CompanyFilterTrait::getOwningCompanyId(?int $resourceCompanyId)`. A tenant always gets their own company. A super-admin gets the resource's company, or their own company if there is none.
  - `ServiceLogController::store` and `MaintenanceScheduleController::store` call it with the vehicle's company (soft-deleted vehicles included) after validation.
  - `getAllUserCompanyId()` itself is unchanged: `VehicleController`/`TrailerController` use it only for tenants, which is correct.
  - This also covers **MNT-03** in the maintenance module.
- **ACL-06: `.htaccess`.** Removed `RewriteCond %{SERVER_PORT} 80` / `RewriteRule ^(.*)$ http://127.0.0.1:8000/$1 [R,L]`.
  - Those lines sent every plain-HTTP visitor to their own machine.
  - They also ran before the `HTTPS` redirect, so the redirect never fired for port 80.
  - Port-80 requests now reach the existing HTTP→HTTPS 301.
  - Nothing else in the file changed, including the `/public` rewrite and the cPanel PHP handler.
  - `config/app.php` already defaults `debug` to `false`.
- **ACL-07: dead code.**
  - Removed the `use App\Http\Middleware\CheckApplicationSession;` import from `bootstrap/app.php`; that class doesn't exist.
  - Deleted `app/Http/Middleware/BlockExpiredLogin.php`, which was never registered and read `$user->subscription`, a relation `User` doesn't have.
  - Deleted `app/Traits/HasSubscription.php`, which was unused and duplicated `User::subscriptions()/activeSubscription()/hasActiveSubscription()` with different rules.
  - The product owner approved the deletions on 2026-10-09.
- **ACL-08: `env()` in Blade.**
  - New config key `app.ga_measurement_id` (from `GA_MEASUREMENT_ID`).
  - `welcome.blade.php` and `layouts/main-layout.blade.php` now fall back to `config('app.ga_measurement_id')` instead of `env(...)`.
  - The variable is documented in `.env.example`.
  - The site-settings value still takes priority, as before.
- **ACL-09: dismissed.**
  - Since USR-03, `super-admin` and `company` are `RoleController::SYSTEM_ROLES`: they can't be renamed or deleted, and `super-admin` always keeps every permission.
  - The tests show that both attempts fail, and that afterwards super-admin access (`role:super-admin` routes, the `Subscribed` bypass) still works and tenants are still locked out.
  - A guard test also checks that every `role:` name used in a route is a protected system role.

## Out-of-scope changes

- `app/Http/Controllers/ServiceLogController.php`, `app/Http/Controllers/MaintenanceScheduleController.php`: one statement each in `store()` (ACL-05). These belong to the maintenance module, so MNT-03 should come out DISMISSED or already fixed when that module runs.
- `app/Http/Controllers/DashboardController.php`, `resources/views/admin/profit.blade.php`: `profit()` and the view were removed (ACL-04). These belong to the dashboard module (DSH-01).
- `resources/views/welcome.blade.php`, `resources/views/layouts/main-layout.blade.php`: `env('GA_MEASUREMENT_ID')` → `config('app.ga_measurement_id')`, one line each (ACL-08). These views belong to the settings module.
- `tests/Feature/Modules/UsersRoles/{RoleErrorMessageTest,RoleManagementTest}.php`: the flash-message helper unserializes php-flasher 2.6 envelopes (ACL-02).
- `phpunit.xml`: added `<ini name="memory_limit" value="512M"/>`.
  - Before this run, the full suite already peaked at 116.5 MB of PHP's default 128 MB. Every Laravel test leaks about 0.2 MB, and the Pest arch tests parse all of `App` (about +40 MB).
  - With this module's tests added, `php artisan test` hit a fatal out-of-memory error. The cause is not in this module's code; any module adding about 70 tests would have hit it too.

## Deferred items (questions for the product owner)

None. All three questions were answered on 2026-10-09:

1. ~~ACL-02: may we run `composer update`, and should `yoeunes/toastr` be removed?~~ **Yes.** Done; see above.
2. ~~ACL-07: OK to delete `BlockExpiredLogin.php` and `HasSubscription.php`?~~ **Yes.** Deleted.
3. ~~Should `/profit` be removed?~~ **Yes, completely.** Removed.

Still open (blocks nothing): a super-admin's maintenance schedule **with no vehicle** still belongs to the super-admin's own company, because there is no other company to take it from. *Should a super-admin have to pick a company (or a vehicle) when creating one?*

## Manual steps

1. No migrations, and no seeder run needed: the permissions used already exist and the `company` role already has them.
2. **Dependencies:** deploy the new `composer.lock` with `composer install --no-dev --optimize-autoloader`, then `php artisan config:cache`, `route:cache` and `view:cache`. Try it on staging first: 87 packages changed, including Laravel 12.52 → 12.69. Click through toastr notifications (e.g. saving a role) and the PDF exports (dompdf 3.1.6).
3. **Production `.env`**: make sure it has `APP_ENV=production` and `APP_DEBUG=false`. The local `.env` has `local`/`true`; production's could not be checked from here. If you use the Google Analytics fallback, set `GA_MEASUREMENT_ID`, then run `php artisan config:cache`.
4. **Production `.htaccess`**: deploy the updated root `.htaccess`. Then check that `http://<domain>/` returns a 301 to `https://<domain>/` (before, it redirected to `http://127.0.0.1:8000/`).
5. The `audit` test fails whenever a new advisory is published, even if nothing in the code changed. Use `--exclude-group=audit` for a run that must not depend on that.

## Observations (not hotspots)

- `GET|PUT storage/{path}` (`storage.local`, `storage.local.upload`) exist because the `local` disk has `serve => true`. Laravel only serves signed temporary URLs there. They are in the public allowlist with that reason, and should be reviewed with the upload modules (sensitive documents on the private disk).
- `Subscribed` lets super-admins through by role name, and `CompanyFilterTrait` does the same. Both are covered by ACL-09's guard test.

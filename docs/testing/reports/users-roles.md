# Module report: `users-roles` (users, roles & permissions)

Run date: 2026-10-09. Tests: `tests/Feature/Modules/UsersRoles` (49 tests, all passing, including the follow-up below).

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| USR-01 | P0 | FIXED | `UserAuthorizationTest.php::USR-01 → it does not let a tenant make themselves super-admin`; `…::it does not let a tenant change another user's email or password`; `…::it forbids a user without users permissions from every users route` (×8 routes, tenant and plain user); `…::it does not let a non-super-admin with users.edit grant the super-admin role`; `…::it does not let a non-super-admin with users.edit change or delete a super-admin`; `…::super-admin keeps full control → it lets a super-admin list, create, update and delete users` (regression guard) | `routes/web.php`, `app/Http/Controllers/UserController.php` |
| USR-02 | P1 | FIXED | `UserSuspendRoutesTest.php::USR-02 → it suspends a user instead of failing with a 500`; `…::it unsuspends a user instead of failing with a 500`; `…::it does not let a super-admin suspend themselves`; `…::it does not let a user deactivate themselves from the edit form`; `…::it returns 404 for an unknown user`; `…::it no longer exposes a 2FA reset route for a feature the app does not have` | `routes/web.php`, `app/Http/Controllers/UserController.php` |
| USR-03 | P2 | FIXED (follow-up implemented, see below) | `RoleManagementTest.php::USR-03 → it refuses to delete a system role` (super-admin, company); `…::it refuses to rename a system role` (super-admin, company); `…::it deletes a custom role and redirects back with a success message`; `…::it returns 404 when deleting an unknown role`; `…::it does not let a user without roles.delete delete a role`; `…::it redirects the show route instead of rendering a blank page`; `…::roles index: delete button → it renders role names as escaped data attributes, not inside an inline onclick`; `…::it hides the delete button for system roles` | `app/Http/Controllers/RoleController.php`, `resources/views/admin/roles/index.blade.php` |
| USR-04 | P1 | FIXED (follow-up implemented, see below) | `PermissionSeederSafetyTest.php` (8 tests, see the follow-up section) | `database/seeders/PermissionSeeder.php`, `database/seeders/DemoUserSeeder.php` (new), `database/seeders/DatabaseSeeder.php` |
| USR-05 | P2 | FIXED | `InactiveUserSessionTest.php::USR-05 → it logs out a user who was deactivated after logging in`; `…::it logs out a user suspended by an admin on their next request`; `…::it leaves active users alone` | `app/Http/Middleware/EnsureUserIsActive.php` (new), `bootstrap/app.php` |
| USR-06 | P3 | FIXED | `RoleErrorMessageTest.php::USR-06 → it shows a generic message when creating a role fails`; `…::it shows a generic message when updating a role fails`; `…::it returns 404 for an unknown role instead of a toast with the model class` | `app/Http/Controllers/RoleController.php` |

No hotspot was dismissed. All six failed on the original code. The failures were 200/302 where 403 was expected, `Call to undefined method UserController::suspend()`, the seeder running `SET FOREIGN_KEY_CHECKS`, and toasts containing `SQLSTATE`.

### What changed

- **USR-01: authorization.** Each `/users` route now requires `permission:users.view|create|edit|delete`, matching the existing `users.*` permissions in `PermissionSeeder`. Only `super-admin` has these. Tenants (`company`) and `user`-role accounts get 403.
  - Defense in depth in `UserController`: `authorizeSuperAdminChanges()` returns 403 when someone who is not a super-admin tries to grant the `super-admin` role (on store or update), or tries to update, suspend or delete an existing super-admin.
  - Without this check, a custom role given `users.edit` through the Roles UI could still promote itself.
- **USR-02: missing methods.** `suspend()` and `unsuspend()` now exist. They set `status` to `inactive` or `active`, the same field the edit form and `LoginRequest` use. They are guarded by `permission:users.edit` and the super-admin check, and they refuse to change your own status.
  - The edit form now also refuses to set your *own* status to `inactive`. With USR-05 in place, doing that would log you out immediately.
  - `users.2fa.reset` is removed. The app has no 2FA (no column, package or view), so the route could only ever return 500. Nothing referenced it.
- **USR-03: role deletion.**
  - `destroy()` now redirects back to the roles list with a toast. Before, it returned an empty page.
  - `destroy()` refuses to delete the system roles. These are `RoleController::SYSTEM_ROLES = ['super-admin', 'company']`, the roles the code checks by name in `hasRole`, `role:super-admin` and registration.
  - `update()` refuses to rename a system role, which would cause the same lockout. Their permissions can still be edited.
  - `show()` redirects to the edit page instead of rendering a blank 200.
- **Roles index view (found while fixing USR-03).**
  - The delete button used `onclick="deleteRole(id, '{{ addslashes(name) }}')"` and put the name into SweetAlert `html:`, so a role name containing `<img onerror>` executed. It now uses `data-action="delete-role" data-url data-name` with one delegated listener, and SweetAlert `text:`.
  - The JS also posted to `/admin/roles/{id}`, but the real route is `/admin/settings/roles/{id}`, so deleting from the UI always returned 404. It now uses `route('admin.roles.destroy')`.
  - The delete button is hidden for system roles.
- **USR-04: seeder.** First fixed with a production guard. That guard was replaced by the non-destructive seeder described in the follow-up below.
- **USR-05: inactive sessions.** The new `EnsureUserIsActive` middleware is appended to the `web` group. If the logged-in user's `status` is `inactive`, it logs them out, invalidates the session, rotates the CSRF token and redirects to login with the same message as `LoginRequest`, or returns 401 for JSON requests. This works with any session driver and also covers "remember me" re-logins.
- **USR-06: error messages.** `RoleController` `store` and `update` now `Log::error()` the exception and show a generic toast ("Failed to create/update role. Please try again."). `update` looks up the role before the `try`, so an unknown id returns 404. Before, it showed a toast containing `No query results for model [Spatie\Permission\Models\Role]`.

## Out-of-scope changes

- `routes/web.php`: added `->middleware('permission:users.*')` to the 8 `/users` routes and removed the `users.2fa.reset` route. No other route changed.
- `app/Http/Middleware/EnsureUserIsActive.php`: new file (USR-05).
- `bootstrap/app.php`: `$middleware->web(append: [EnsureUserIsActive::class])`. This adds a status check on every web request made by a logged-in user. It runs no extra query, because the user is already loaded by `auth`.

## Deferred items (questions for the product owner)

All three were answered on 2026-10-09 and implemented. See "Follow-up" below.

1. ~~Seeder destructiveness~~: **move the demo login to a local-only seeder.**
2. ~~Super-admin permissions~~: **the super-admin role always keeps every permission.**
3. ~~Self-demotion~~: **block it.**

## Follow-up: product decisions implemented

| Decision | Result | Test file::test name | Files changed |
|---|---|---|---|
| 1. `PermissionSeeder` is non-destructive; demo login in a local-only seeder | DONE | `PermissionSeederSafetyTest.php::USR-04: PermissionSeeder is a non-destructive sync → it keeps existing users and companies and creates no demo login, even in production`; `…::it creates every permission and the default role mapping on an empty database`; `…::it is idempotent and adds a permission that is new in the code`; `…::it keeps extra permissions an admin gave the company role in the Roles UI`; `…::it gives back every permission to a super-admin role that lost some`; `…::USR-04: DemoUserSeeder (demo super-admin) is local only → it refuses to run outside local and testing` (production, staging); `…::it creates the demo super-admin and company locally, once` | `database/seeders/PermissionSeeder.php`, `database/seeders/DemoUserSeeder.php` (new), `database/seeders/DatabaseSeeder.php` |
| 2. super-admin role always has every permission | DONE | `RoleManagementTest.php::USR-03 → it keeps every permission on the super-admin role whatever the form sends`; `…::it still lets a super-admin change the permissions of the company role` (regression guard); `…::it shows the super-admin permissions as locked on the edit page` | `app/Http/Controllers/RoleController.php`, `resources/views/admin/roles/edit.blade.php` |
| 3. You can't remove your own super-admin role | DONE | `UserAuthorizationTest.php::USR-01: a super-admin cannot remove their own super-admin role → it refuses to drop super-admin from your own roles`; `…::it lets you keep super-admin while adding other roles to yourself`; `…::it still lets a super-admin demote another super-admin` | `app/Http/Controllers/UserController.php` |

How it works:

- **`PermissionSeeder`** no longer truncates anything and creates no users. It:
  - runs `Permission::findOrCreate` for every name in `permissionNames()`;
  - syncs the `super-admin` role to all permissions;
  - *adds* the default permissions to the `company` role (`givePermissionTo` attaches only what's missing), so extras an admin granted in the Roles UI are kept;
  - ensures the `user` role exists.

  It is idempotent and safe to run in production. **This is how new permissions reach production:** `php artisan db:seed --class=PermissionSeeder --force`. It never removes a permission that was dropped from the code. Delete those by hand if needed.
- **`DemoUserSeeder`** (new) creates `superadmin@gmail.com` / `12345678` and its "Super Admin Company" with `firstOrCreate`, so re-running it doesn't duplicate them. It throws a `RuntimeException` unless `APP_ENV` is `local` or `testing`. `DatabaseSeeder` calls it right after `PermissionSeeder`, so `migrate:fresh --seed` locally gives the same demo login as before. In any other environment, `db:seed` stops at this step on purpose. The rest of `DatabaseSeeder` is demo data with known passwords too (see Notes).
- **super-admin permissions:** `RoleController::update` ignores the submitted permission list for the `super-admin` role and syncs every permission. The edit page shows all boxes ticked and disabled, with "The super-admin role always has every permission." Other roles, including `company`, stay editable. There's still no `Gate::before` bypass; permissions are enforced through the role.
- **Self-demotion:** `UserController::update` refuses, with a toast and a redirect back, when you are a super-admin and the submitted roles drop `super-admin` from your own account. This also protects the last super-admin: only a super-admin can change another super-admin (USR-01), and nobody can demote themselves, so at least one always remains. One super-admin can still demote another.

## Out-of-scope changes (follow-up)

- `database/seeders/DemoUserSeeder.php` (new) and `database/seeders/DatabaseSeeder.php` (one `$this->call(DemoUserSeeder::class)` line). Seeders other than `PermissionSeeder` are outside the module scope.
- `tests/Support/Actors.php` was **not** changed. It still replays `PermissionSeeder`'s constants. The new test `…::it creates every permission and the default role mapping on an empty database` asserts that the seeder produces the same mapping, so drift between the two will fail a test.

## Manual steps

- No migrations and no config changes.
- After deploy, any user who is `inactive` but still logged in is logged out on their next request (USR-05).
- Production: run `php artisan db:seed --class=PermissionSeeder --force` whenever permissions change in code, for example now, so that a super-admin role edited in the UI gets every permission back.
- Never run `DemoUserSeeder` or a plain `db:seed` outside local. Both now refuse to run.
- If a non-local database already has `superadmin@gmail.com` / `12345678` from the old seeder, change that password or delete the account. The new seeders don't touch existing users.

## Notes / observations (not fixed)

- The users index and edit views show Edit and Delete buttons without `@can` checks. Only users with `users.*` can reach those pages now, so this is cosmetic.
- `PUT /admin/settings/roles/{role}` uses `{role}`, while the controller takes `string $id`. It works because the binding is positional. It is left unchanged.
- `CompanySeeder` (called by `DatabaseSeeder` after `DemoUserSeeder`) creates `company1@gmail.com` / `company2@gmail.com` with password `12345678` and uses `User::create`. It is demo data with known passwords. It is only reached locally now, because `DemoUserSeeder` throws first elsewhere. Re-running `db:seed` without `migrate:fresh` fails on duplicate emails. The old seeder wiped `users` first, so this didn't happen before. It is outside scope and was left unchanged.
- Larastan on the scope paths (`UserController`, `RoleController`, `EnsureUserIsActive`, `PermissionSeeder`, `DemoUserSeeder`, `DatabaseSeeder`, `config/permission.php`): no errors.

## Test run comparison

- Baseline `php artisan test`: 10 failed, 142 passed.
- After: 10 failed, 178 passed. The same 10 tests fail as in the baseline (`Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4, `ProfileTest` ×5), so there are no regressions.
- `tests/Feature/Modules/UsersRoles`: 36 passed.
- After the follow-up: 10 failed (the same 10), 191 passed. `tests/Feature/Modules/UsersRoles`: 49 passed.

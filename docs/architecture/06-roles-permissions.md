# 06 · Roles and permissions

**Decision (13 October 2026).** Keep `spatie/laravel-permission` with **teams** (`team_id = company_id`) for the schema and for role and permission management. Authorization checks are made **database-first**: one indexed query per request returns the user's effective permissions in their own team. Spatie's global permission-with-roles cache is **not** used for tenant authorization.

This decision rests on a load test at 2,000 and 5,000 companies (section 2). A custom RBAC was not needed, because the database-first approach meets every requirement while keeping Spatie's tables, APIs and middleware.

## 1. Today [V]

- `config/permission.php` has `teams => false`. Three global roles exist: `super-admin`, `company` and `user`.
- `PermissionSeeder` builds `module.action` names. The `company` role gets the operational modules plus `companies.edit` (`PermissionSeeder.php:44-46`).
- Only the super-admin manages users and roles (`UserAuthorizationTest.php:57-75`). A role edit applies to every tenant (`RoleManagementTest.php:63-72`).
- Routes use Spatie's `permission:` middleware in about 110 places (`routes/web.php`).

## 2. Architecture decision record: how permissions are checked at 2,000+ companies

### 2.1 How stock Spatie checks a permission [V]

1. `hasPermissionTo()` → `hasPermissionViaRole()` → `$this->hasRole($permission->roles)` (`Traits/HasPermissions.php:308-314`).
2. `$permission->roles` comes from the registrar's cache. That cache holds **every permission together with every role, in every team, that has it** (`PermissionRegistrar.php:302-367`, `getPermissionsWithRoles()` = `Permission::with('roles')`).
3. With teams, the role list per permission therefore grows with the number of companies. The whole payload is read and unserialized on every request that checks a permission.
4. Any change to a role's permissions, in any company, clears the cache for everyone. The next request rebuilds it.

### 2.2 Load test

**Script:** `docs/architecture/spikes/permission-scale-spike.php`. It is standalone and ran against a throwaway MySQL database that has since been dropped.

**Environment:** MySQL 9.3.0 and PHP 8.3 on an Apple-silicon laptop, with Laravel 12 and Spatie 6.25 taken from this repository.

**Data per company:** 7 system roles, 110 permissions and 7 users, shaped like the catalogue in section 5.

| Measurement | Stock Spatie teams, database cache | Stock Spatie teams, Redis cache | Database-first (this design) |
|---|---|---|---|
| Data at 2,000 companies | 14,000 roles, 858,000 role-permission rows | same | same |
| Cache rebuild after any role edit | **86.5 s, +3.6 GB memory** | **90.2 s, +3.6 GB** | none (cache holds 110 permissions, 0 roles) |
| Stored cache payload | **13.0 MB** | **13.0 MB** | ~20 KB [A] (110 rows) |
| Per request: load the permission data | p50 168 ms, p95 258 ms | p50 145 ms, p95 161 ms | — |
| Per permission check | p50 15.0 ms, p95 18.9 ms | p50 14.0 ms, p95 15.1 ms | resolve once, then an in-memory lookup |
| Per request: resolve all permissions + 3 checks, 2,000 companies (4,176 requests) | — | — | **p50 0.215 ms, p95 0.293 ms, p99 0.315 ms** |
| Same at 5,000 companies, 2.14 M role-permission rows (4,644 requests) | not run: rebuild would need roughly 9 GB [A, by extrapolation] | not run | **p50 0.226 ms, p95 0.353 ms, p99 0.500 ms** |
| 8 parallel workers × 5,000 resolutions, 2,000 companies | — | — | **14,563 /s**; worst worker p95 0.82 ms, p99 1.09 ms |
| 8 parallel workers × 5,000 resolutions, 5,000 companies | — | — | **15,274 /s**; worst worker p95 0.76 ms, p99 0.88 ms |
| Query plan (`EXPLAIN`) | — | — | `model_has_roles` ref (1 row) → `roles` eq_ref → `role_has_permissions` ref (~57 rows) → `permissions` eq_ref |

**Conclusion.**
- **Stock Spatie teams is unsuitable at the target scale.** Every request pays 150+ ms just to load permission data. A single role edit makes the next request rebuild the cache, needing about 90 s and 3.6 GB, which is far beyond any PHP memory limit. Redis doesn't change this; the cost is unserializing and hydrating the payload, not the store.
- **The database-first check is flat** from 2,000 to 5,000 companies, well under 1 ms, and needs no cross-request cache.

### 2.3 Isolation tests (same run)

| Test | Result |
|---|---|
| User of company A, evaluated in team A | Has their permissions |
| Same user evaluated in team B | 0 permissions |
| Assigning a role of team B with pivot team A | **Rejected by the database** (composite FK `(team_id, role_id)` → `roles(team_id, id)`) |
| Assigning a role with `team_id = NULL` in a company team | **Rejected by the database** (same FK) |
| Stock Spatie team scope (`team_id IS NULL OR team_id = A`) and a null-team role | Spatie **would** see it. Hence the "no null-team roles" rule plus the integrity query. |
| A stray pivot row recording user A under team B (an application bug) | Never grants anything in team A. The integrity query finds it. |
| Company A and company B each create a role named **Admin** (run 2, 13 Oct 2026, 2,000 companies) | Two separate rows, one per team. Same names in different companies are allowed by the unique key `(team_id, name, guard_name)`. |
| Company A changes its Admin's permissions (`syncPermissions`) | Only A's Admin changes. B's Admin keeps exactly its own permissions; B's user gains nothing. |
| Acting as company A, load company B's Admin by id | **Refused** (`RoleDoesNotExist`): Spatie's team scope hides other teams' roles |
| Company B deletes its Admin | A's Admin and A's user are untouched. B's user loses only B's permissions. |
| Company B looks up "Admin" by name afterwards | Does not find A's Admin |

**More roles per user (run 2).** Resolution grows only with the number of the user's own roles, never with the number of companies:

| Roles held by one user (60 permissions each) | p50 | p95 |
|---|---|---|
| 1 | 0.252 ms | 0.318 ms |
| 3 | 0.342 ms | 0.381 ms |
| 6 | 0.563 ms | 0.998 ms |

### 2.4 Spatie management still works without the cache (same run)

All of these behaved correctly with the slim registrar (permissions cached without roles):
- `Role::create` gets the current team id;
- `givePermissionTo`, `syncPermissions`, `assignRole`, `removeRole`, direct `givePermissionTo` on a user;
- `hasRole`.

Stock `hasPermissionTo()` then returns **false**, because it fails closed. It must therefore always go through the resolver (section 4.3), which also covers `hasAnyPermission`, `hasAllPermissions` and `checkPermissionTo`, since they all call `hasPermissionTo` [V].

### 2.5 Why not a custom RBAC

- The database-first approach on Spatie's own tables meets the isolation, scale and simplicity requirements.
- A custom RBAC would mean rewriting role management, the `permission:` and `role:` middleware, the Blade directives and every test that uses them, for no additional security or performance.
- Spatie stays for the schema, management APIs, middleware and Blade directives. It is no longer used to decide a permission check.

### 2.6 Constraints found during the spike [V]

- Spatie binds its registrar in `PermissionServiceProvider::boot()` (`PermissionServiceProvider.php:43`), not in `register()`. The override must therefore be bound **after** providers boot, in `AppServiceProvider::boot()`. Binding it in `register()` has no effect.
- Turn off Spatie's own Gate hook (`register_permission_check_method = false`). Otherwise its `Gate::before` would call stock `checkPermissionTo` before the app's checks.

## 3. Configuration

| Setting | Value | Why |
|---|---|---|
| `teams` | `true` | One role set per company |
| `team_foreign_key` | `team_id` | The company id, or `0` for the platform |
| `register_permission_check_method` | **`false`** | The app's single `Gate::before` decides (section 6) |
| `models.permission`, `models.role` | `App\Models\Permission`, `App\Models\Role` (extend Spatie's) | Extra columns |
| Registrar | `App\Authorization\CatalogueRegistrar` extends `PermissionRegistrar`; `getPermissionsWithRoles()` returns permissions **without** roles. Bound in `AppServiceProvider::boot()`. | The cache holds only the permission catalogue (about 110 rows), so its size is the same at any number of companies |
| `cache.store` | The default store (`database`) is fine; nothing large is cached | — |
| Guards | `web` (tenant users including drivers; Sanctum tokens resolve to `web`), `platform` (platform staff) | Spatie keys permissions and roles by guard |

## 4. Tables and checks

### 4.1 Tables (Spatie, extended)

| Table | Added to Spatie's teams schema | Keys |
|---|---|---|
| `permissions` | `feature_key` (FK → `features.key`, nullable, RESTRICT), `is_write`, `self_scoped`, `description` | UK `(name, guard_name)` |
| `roles` | `system_key` (nullable), `is_system`, `description` | UK `(team_id, name, guard_name)`, `(team_id, system_key)`, **`(team_id, id)`** |
| `model_has_roles` | — | PK `(team_id, role_id, model_id, model_type)`. **Composite FK `(team_id, role_id)` → `roles(team_id, id)` ON DELETE CASCADE**, which replaces Spatie's single `role_id` FK. Index `(model_type, model_id, team_id)` for the resolver. |
| `model_has_permissions` | — | PK `(team_id, permission_id, model_id, model_type)`. FK `permission_id` CASCADE. Index `(model_type, model_id, team_id)`. |
| `role_has_permissions` | — | PK `(permission_id, role_id)`. Index `role_id`. Both FKs CASCADE. |

`roles.team_id` has no FK to `companies`, because `0` is not a company. The company purge job removes a company's roles.

### 4.2 Resolver query

`App\Authorization\TenantPermissionResolver::names($user, $teamId)` runs this once per request, memoised in `TenantContext`:

```sql
SELECT p.name FROM model_has_roles mhr
  JOIN roles r ON r.id = mhr.role_id AND r.team_id = mhr.team_id
  JOIN role_has_permissions rhp ON rhp.role_id = r.id
  JOIN permissions p ON p.id = rhp.permission_id AND p.guard_name = :guard
 WHERE mhr.model_type = :type AND mhr.model_id = :user AND mhr.team_id = :team
UNION
SELECT p.name FROM model_has_permissions mhp
  JOIN permissions p ON p.id = mhp.permission_id AND p.guard_name = :guard
 WHERE mhp.model_type = :type AND mhp.model_id = :user AND mhp.team_id = :team
```

- `:team` is always `users.company_id` for tenant users (or `0` for platform staff). It is never taken from the request.
- The memo is cleared whenever a role or permission of that user changes within the same request, and at the end of every queued job.
- **No cross-request cache.** At 0.2–0.5 ms per request one isn't needed, which also removes a whole class of stale-permission bugs.

### 4.3 Routing every check through the resolver

- `App\Models\User` and `App\Models\PlatformUser` override `hasPermissionTo()` to ask the resolver for their own team. Spatie's `checkPermissionTo`, `hasAnyPermission` and `hasAllPermissions` call `hasPermissionTo` [V], so they follow.
- The `permission:` middleware calls `canAny()` → Gate → `Gate::before` (section 6) [V].
- `@can`, `$user->can()` and policies go through the same Gate.
- An architecture test forbids any other code path reading `$permission->roles` for an authorization decision.

### 4.4 Rules the database can't express

These are checked by code, by tests, and by the nightly `integrity:check` (every query must return no rows):

| Rule | Integrity query |
|---|---|
| No role has a null team | `SELECT id FROM roles WHERE team_id IS NULL` |
| Guard `platform` ⇔ team `0` | `SELECT id FROM roles WHERE (guard_name='platform') <> (team_id=0)` |
| A tenant user's role pivot team equals their company | `SELECT … FROM model_has_roles m JOIN users u ON m.model_type='App\\Models\\User' AND u.id=m.model_id WHERE m.team_id <> u.company_id` |
| The same, for direct permissions | Same query against `model_has_permissions` |
| The Owner role is held by exactly `companies.owner_user_id` | [11-migration-strategy.md](11-migration-strategy.md) §6 query 3 |
| A tenant role holds only `web` permissions | Join `role_has_permissions` on a guard mismatch |

A CHECK constraint on `roles` was not used. `roles.team_id` is referenced by a cascading composite FK, and MySQL forbids CHECK constraints on columns involved in referential actions.

## 5. Tenant permission catalogue (guard `web`)

Names keep today's `module.action` form wherever the module already exists, so most `permission:` route entries don't change.

**R / W** is the `is_write` flag. Read permissions stay allowed in the `read_only` access state. **Own** means `self_scoped`: policies limit those permissions to the user's linked driver record.

| Feature | Permissions |
|---|---|
| core (account) | `company.view` R, `company.edit` W (profile, logo, policy PDFs; replaces `companies.edit` and `policy-pdf.*`), `users.view` R, `users.create` W, `users.edit` W, `users.deactivate` W, `billing.view` R, `billing.manage` W, `document-types.toggle` W |
| `custom_roles` | `roles.view` R, `roles.create` W, `roles.edit` W, `roles.delete` W, `permissions.grant` W |
| `drivers` | `drivers.view` R, `drivers.create` W, `drivers.edit` W, `drivers.delete` W, `drivers.hire` W |
| `driver_compliance` | `drivers.dashboard` R, `driver-documents.upload` W, `driver-documents.delete` W |
| `fleet` | `vehicles.*`, `trailers.*`, `asset-groups.*` (view R; create, edit, delete W) |
| `fleet_compliance` | `fleets.dashboard` R, `fleets.edit` W |
| `maintenance` | `maintenance.*`, `scheduled.*` (view R; create, edit, delete W) |
| `compliance_reminders` | `reminders.send` W |
| `document_folders` | `folders.view` R, `folders.manage` W, `documents.upload` W, `documents.delete` W |
| `zip_export` | `exports.create` R, `exports.download` R |
| `trips` | `trips.view` R, `trips.create` W, `trips.edit` W, `trips.delete` W, `trips.assign` W, `trips.confirm-number` W, `shippers.view` R, `shippers.manage` W; own: `trips.view.own` R, `trips.drive.own` W |
| `pre_trip_inspections` | `inspections.view` R; own: `inspections.perform.own` W |
| `trip_events` | `trip-events.view` R, `trip-events.manage` W; own: `trip-events.create.own` W, `fuel.create.own` W |
| `gps_tracking` | `tracking.view` R; own: `tracking.report.own` W |
| `mobile_app` | `mobile.access` R |
| `messaging` | `messages.view` R, `messages.send` W, `messages.broadcast` W |
| `time_off` | `time-off.view` R, `time-off.approve` W; own: `time-off.request.own` W |
| `driver_pay` | `pay.view` R, `pay.manage` W |
| `timesheets` | `timesheets.view` R, `timesheets.approve` W; own: `timesheets.view.own` R, `time-entries.create.own` W |
| `profit_loss`, `year_end_reports` | `reports.view` R, `reports.export` R |
| `quickbooks` | `integrations.manage` W, `payroll.export` W |

**Removed from tenants.** These become platform permissions, as LKP-01 decided:
- `settings.*`, `companies.create/view/delete`;
- the lookup modules;
- `document-types.create/edit/delete`;
- `subscription.*`, now `billing.*`.

`permission.*` and `asset-histories.*` are dropped if they are still unused at implementation time [A].

## 6. System roles per company

Provisioning creates these roles in the company's team from code templates (`App\Authorization\RoleTemplates`).

| Role | `system_key` | Editable? | Permissions |
|---|---|---|---|
| Owner | `owner` | Locked | Every tenant permission, limited in practice by entitlements. Held only by `companies.owner_user_id`. |
| Administrator | — (`is_system`) | Editable; deletable if unused | Everything except `billing.manage` |
| Dispatcher | — | Editable | `trips.*`, `shippers.*`, `drivers.view`, `vehicles.view`, `trailers.view`, `asset-groups.view`, `tracking.view`, `trip-events.*`, `inspections.view`, `messages.*`, `time-off.view` |
| Office / Compliance | — | Editable | `drivers.*`, `drivers.dashboard`, `driver-documents.*`, `vehicles.*`, `trailers.*`, `asset-groups.*`, `fleets.*`, `maintenance.*`, `scheduled.*`, `folders.*`, `documents.*`, `exports.*`, `reminders.send` |
| Driver | `driver` | Locked | `trips.view.own`, `trips.drive.own`, `inspections.perform.own`, `trip-events.create.own`, `fuel.create.own`, `tracking.report.own`, `timesheets.view.own`, `time-entries.create.own`, `time-off.request.own`, `messages.view`, `messages.send`, `mobile.access` |
| Support (read) | `support_read` | Locked, not assignable by tenants | Every R permission |
| Support (read/write) | `support_read_write` | Locked, not assignable by tenants | R + W, minus `billing.*`, `users.*`, `roles.*`, `permissions.grant`, `drivers.delete`, `documents.delete`, `driver-documents.delete` |

An **owner-operator** is the Owner user linked to their own driver record. A user may hold several roles, for example Dispatcher + Driver.

## 7. Authorization order (`Gate::before`)

```php
Gate::before(function ($user, string $ability) {
    // 1. Actor and guard: platform staff only in platform context; tenant users only in tenant context
    // 2. TenantContext::companyId() must equal $user->company_id (tenant users)
    // 3. $permission = Permission::fromCatalogue($ability, $guard) — unknown ability → null (policies decide)
    // 4. Entitlements::allows($company, $permission->feature_key) or return false
    // 5. ! $permission->is_write || AccessState::allowsWrite($company) or return false
    // 6. return TenantPermissionResolver::has($user, $ability)   // database-first, team = company
});
```

Policies then add the "own record" rules: Driver-role users only see their own trips and events, and a user cannot change their own roles.

## 8. Escalation rules (`RoleAssignmentService`, `RoleService`, `OwnershipService`)

1. **Same company only.** Role and user ids are validated against the actor's company (`roles.team_id = company`). The composite FK backs this up.
2. **Grant only what you hold.** New or edited roles, and direct grants, may contain only permissions in the actor's resolved set.
3. **Assign only subsets.** A role can be assigned only if all its permissions are in the actor's resolved set.
4. **No self-service.** Nobody edits their own roles or direct permissions.
5. **Locked roles** (`system_key` set) can't be edited, deleted, or assigned through the UI.
   - The Owner role moves only through `OwnershipService::transfer()`.
   - The Driver role is assigned only by linking a driver record.
   - The Support roles are only used by support sessions.
6. **Protecting the Owner.** Only the Owner can change the Owner user's roles, email or status.
7. **Seat limit.** `max_users` is checked when inviting, accepting, or re-activating a user.
8. **Audit.** Every change writes to `audit_logs` and clears that user's resolver memo.
9. **Platform side.** The same rules apply within team `0`. At least **two** active Platform Owners must always exist (Q1, decided): the system refuses to demote or disable an Owner when that would leave fewer than two.

## 9. Runtime safety

| Concern | Handling |
|---|---|
| Team per request | `ResolveTenant` calls `setPermissionsTeamId($companyId)`. The platform area uses `0`. |
| Queue workers | The `TenantAware` job middleware sets the team id and context, clears the resolver memo, and resets both in a `finally` block |
| Octane | `TenantContext` and the resolver memo are request-scoped (`app()->scoped`) |
| Catalogue cache | About 110 rows, cleared when the seeder changes permissions. Its size doesn't depend on the number of companies. |

## 10. Legacy roles are not carried over

The three roles in today's code (`super-admin`, `company`, `user`) belong to the **old** system. They have **no relationship** with the new design:
- **No reuse.** They are not migrated, mapped or renamed, and their names are not reused as `system_key` values.
- **Removed from seeding.** The rewritten `PermissionSeeder` no longer creates them.
- **Code that goes.** Every check that names them is deleted when its controller moves to the new design: `hasRole('super-admin')`, `role:super-admin`, and `RoleController::SYSTEM_ROLES`.
- **Clean start.** There is no real data (decision log), so no legacy role or assignment row exists after the rebuild. The nightly integrity check also alerts if a role named `super-admin`, `company` or `user` ever appears with a null team.

The new system starts from zero:
- **Platform staff** get the platform roles of [04](04-platform-administration.md) §3 (team `0`, guard `platform`).
- **Each company** gets its own copies of the templates in section 6 (team = that company). It can then create, edit and delete its own roles independently. Roles with the same name in two companies are different rows, so changing one never changes the other (§2.3).

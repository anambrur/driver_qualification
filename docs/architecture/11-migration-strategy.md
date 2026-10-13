# 11 · Migration strategy

## 1. Starting point

- **No real customer data exists.** The product owner confirmed this on 11 October 2026 (PO-19).
- Commit `d1d4d8d` already folded the alter migrations back into the create-table files.
- So the transition is a **schema rebuild**, not a data migration:
  - Edit the create-table migrations.
  - Add new create-table migrations.
  - Rebuild every environment with `php artisan migrate:fresh --seed`.
- From the **first production deploy** onwards, rule 1.6 of PRODUCT_DECISIONS comes back into force: new migrations only, each additive and reversible.

Section 7 covers the case where a staging database turns out to hold data worth keeping.

## 2. Before rebuilding any environment

1. **Back up first.** On each shared environment (staging, demo), take a database dump and archive `storage/app/private` and `storage/app/public`, even if the data is test data.
2. **Wipe old files.** Clear both storage folders. Commit `d1d4d8d` removed the migrations that moved old files to the private disk and encrypted old SSNs ([01](01-current-architecture-assessment.md) R10). Files left from older builds would stay publicly reachable under `/storage/...`.
3. **Database engine and version: MySQL 8.0.16 or later (decided D7a).** CHECK constraints need it. Generated-column and composite-FK behaviour must be verified on that version (Phase 0 spike).
4. **Permission checks are database-first.** No special cache store is needed. The registrar override must be bound in `AppServiceProvider::boot()`, because Spatie binds its registrar in `boot()` ([06](06-roles-permissions.md) §2.6).

## 3. Migration order

The order follows foreign-key dependencies. "Edited" means an existing create-table file is changed; "new" means a new file.

The new tables arrive **with the phase that needs them** ([13-implementation-roadmap.md](13-implementation-roadmap.md)). Phase 0 creates groups A–J and O, plus `stored_files`.

| Group | Migrations | Notes |
|---|---|---|
| A. Framework | `create_cache_table`, `create_jobs_table` (existing) | Unchanged |
| B. Reference | `countries`, `states` (edited: unique `(country_id, code)`); lookups (edited: unique `name`, `is_active`); `document_types` (edited: `system_key`, `requires_expiry`, unique `(module, name)`) | No dependencies |
| C. Platform | `platform_users` + `platform_password_reset_tokens` (new); `features` (new); `plans` (edited); `plan_prices`, `plan_features` (new); `site_settings` (edited; `trial_plan_id` FK) | `features` must exist before `permissions` |
| D. Tenancy core | `companies` (edited, **without** the owner FK); `users` (moved after companies; `company_id` FK, unique `(company_id, id)`); `add_owner_fk_to_companies` (new: composite `(id, owner_user_id)` → `users(company_id, id)`); `company_settings`; `sessions`, `password_reset_tokens` | `users` today sits in `0001_01_01_000000` [V]. It moves into a migration after `companies`, and the 0001 file keeps only the framework session and reset tables. |
| E. Authorization | `create_permission_tables` (edited: teams on, extra columns, unique `(team_id, id)` on roles, composite FK on `model_has_roles`) | Requires `features`. `config/permission.php` must have `teams = true` before it runs. |
| F. Files | `stored_files` (new) | Needs `companies` and `users` |
| G. Drivers | `drivers` (edited); history tables (edited); `driver_documents` (edited); `driver_compliance_documents` (edited) | Needs `stored_files` |
| H. Fleet | `vehicles`, `trailers`, `asset_groups` (edited); `vehicle_documents`, `trailer_documents` (edited); `policy_pdfs` (edited); `company_document_type` | — |
| I. Maintenance | `service_logs`, `service_log_category`, `service_documents`, `maintenance_schedules` (edited) | `service_logs.maintenance_schedule_id` → `maintenance_schedules` must be created first, or added as a separate FK migration |
| J. Billing | `subscriptions`, `payments`, `subscription_notification_logs` (edited, re-keyed); `company_feature_overrides`, `stripe_events` (new) | — |
| K. Operations (Phase 2–3) | `shippers`, `trip_number_sequences`, `trips`, `trip_stops`, `inspection_templates`, `inspection_template_items`, `inspections`, `inspection_results`, `trip_events`, `fuel_purchases`, `location_pings`, `vehicle_last_locations` | — |
| L. Money (Phase 4) | `driver_pay_rates`, `expenses`, `timesheets`, `time_entries`, `payroll_runs`, `payroll_run_lines` | `time_entries` after `timesheets` |
| M. Documents (Phase 1, then extended) | `folders`, `documents` | The subject FKs to trips, events, fuel, expenses and inspections are added by **follow-up migrations** in the phase that creates those tables, so Phase 1 doesn't depend on Phase 2–4 tables |
| N. Communication and integrations (Phase 1–4) | `social_accounts` (P1), `company_invitations` (P0), `device_tokens` + `personal_access_tokens` (P3), `conversations`, `conversation_participants`, `messages` (P3), `time_off_requests` (P4), `integration_*`, `external_references` (P4), `exports` (P1) | — |
| O. Audit and support (Phase 0) | `audit_logs`, `platform_support_sessions` | — |

**SQLite (tests).** Composite FKs, CHECK constraints and generated columns are supported. Adding the companies owner FK after both tables exist depends on Laravel's SQLite table rebuild, so verify it in the Phase 0 spike [A]. The fallback is to enforce it in the app and in an integrity test.

## 4. Seeders

| Seeder | Content | Where it runs |
|---|---|---|
| `FeatureRegistrySync` | Upserts the `App\Features\Feature` enum into `features` | Every environment, every deploy |
| `PermissionSeeder` (rewritten) | Tenant permissions (`web`) and platform permissions (`platform`), each with `feature_key`, `is_write`, `self_scoped`. Stays idempotent and non-destructive, as today [V]. Does **not** create the legacy `super-admin`, `company` or `user` roles ([06](06-roles-permissions.md) §10). | Every environment |
| `PlatformRoleSeeder` | Platform roles in team `0`, guard `platform` | Every environment |
| `PlatformOwnerBootstrap` (artisan command, not a seeder) | Creates the first **two** Platform Owners from given emails. Support sessions need two (decided Q1). Refuses an email that already exists in `users` (D3a). | Once per environment |
| `PlanSeeder` (rewritten) | Plans, prices and plan features mirroring today's tiers ([05](05-plans-features-entitlements.md) §2). Stripe Price ids are created by `plans:sync-stripe` with **test keys only** outside production. | Every environment (prices in production by the Platform Owner) |
| Lookup seeders, `CountryStateSeeder` | Existing [V] | Every environment |
| `DocumentTypeSeeder` | Existing, plus the system type `medical_examiner_certificate` | Every environment |
| `InspectionTemplateSeeder` | Pre-trip template v1 (Phase 2) | Every environment |
| `DemoSeeder` (replaces `DemoUserSeeder`, `CompanySeeder`, `DriverSeeder`, …) | Two Platform Owners, plus two demo companies built **through `CompanyProvisioner`** | Local and staging only |

`CompanyProvisioner` is the only code path that creates a company. Registration, platform "create company", seeders and test factories all use it, so every company gets the same roles, settings, sequences and support user.

## 5. Keeping features working while code moves

The code changes in this order. Each step ships with the full test suite green.

1. **Context first.** Add `TenantContext`, `ResolveTenant` and `users.company_id`. Make `CompanyFilterTrait` read from `TenantContext` internally, so existing controllers behave the same while the source of the tenant changes. Remove the trait's per-instance memo (the Octane risk).
2. **Scopes model by model.** Add `BelongsToCompany` to each tenant model with its isolation test, starting with drivers and their documents, then fleet, maintenance and asset groups. `applyCompanyFilter` calls become no-ops and are removed afterwards.
3. **Policies.** Replace `authorizeCompanyAccess` with policies, controller by controller. Foreign ids now return 404 everywhere.
4. **Authorization.** Turn on Spatie teams and the app-level `Gate::before`. Rename the changed permissions in `routes/web.php` (for example `companies.edit` becomes `company.edit`). Rebuild the Users and Roles screens per company.
5. **Billing.** Move billing to the company: Stripe customer, checkout, webhook inbox, the `subscribed` middleware and the admin screens.
6. **Platform area.** Add the `platform` guard and `/platform` area. Move plans, subscriptions, companies, lookups and settings there. Delete the super-admin branches from tenant code.
7. **Entitlements and limits.** Add the `feature:` middleware and the `Limits` checks.

### Tests that change by design

| Test | Today it asserts | Target |
|---|---|---|
| `UsersRoles/UserAuthorizationTest` | Owners can't manage users | Owners and admins manage users **in their own company**. Cross-company access → 404. |
| `UsersRoles/RoleManagementTest` (`:63-72`) | Editing the `company` role affects every tenant | Roles are per team. Editing company A's role never changes company B. |
| `UsersRoles/PermissionSeederSafetyTest` | The seeder keeps global role grants | The seeder only adds permissions and never touches team roles |
| `AccessControl/SuperAdminCompanyAttributionTest`, `SuperAdminRoleNameTest` | The super-admin works inside tenants | The platform user can't reach tenant routes. A support session can, and is audited. |
| `AccessControl/RouteGuardMatrixTest` | The allow-list of routes without `permission:` (billing) | Updated list; `/platform/*` must have `auth:platform`; tenant routes need `ResolveTenant` |
| `FleetCompliance/FleetComplianceDecisionsTest` (PO-02), `Maintenance/MaintenanceDecisionsTest` (PO-07), `FleetAssets/FleetAssetsUpdateTest` (FLT-03 / PO-08) | Super-admin company pickers and moves | Per D11: done inside a support session, or by the platform transfer operation |
| `Billing/*` (`WebhookIdempotencyTest`, `CheckoutSuccessOwnershipTest`, `TrialAbuseTest`, `WebhookSharedAccountTest`, `AccessibleParityTest`) | Per-user billing | Per-company billing; inbox idempotency by event id; ordering guard; trial per company and email |
| `SubscriptionLifecycle/DashboardGateTest`, `ExpiryReminderTest` | Per-user access and recipients | Per-company access state; Owner + `billing_email` recipients |
| `Companies/CompanyDestroyTest`, `Auth/AuthModuleTest` (AUTH-04) | Deactivation by the owner or super-admin | Same behaviour (PO-13, PO-17), now through `companies.status`, which blocks all users |

### New test suites

- `Tenancy/IsolationMatrixTest`: for **every** tenant route with an id parameter, a user of company B gets 404 on company A's id. Generated from the route list.
- `Tenancy/ScopeFailClosedTest`: a tenant model queried without a context throws.
- `Tenancy/JobContextTest`: a job for company A followed by a job for company B in the same worker leaks nothing (team id, context).
- `Authorization/EscalationTest`: grant-only-what-you-hold, subset assignment, no self-edit, locked roles.
- `Authorization/ResolverTest`: the database-first resolver gives the same answers as the role definitions; another team's pivot never grants; a null-team role can't be assigned (composite FK); stock `hasPermissionTo` is routed to the resolver; an architecture test bans reading `$permission->roles` for decisions.
- `Authorization/PermissionScaleTest` (MySQL CI job, nightly): seeds 2,000 companies and asserts resolver p95 < 5 ms. Based on `docs/architecture/spikes/permission-scale-spike.php`.
- `Platform/AssetTransferTest`: the target gets copies within its limits, the source keeps read-only history, file copies are rolled back on failure.
- `Platform/EmailIdentityTest`: the same email can't be both a platform and a tenant login, from either side.
- `Payroll/OvertimeTest`: daily thresholds, midnight split, weekly reclassification without double counting, exempt drivers, and the regular-rate premium for mileage pay.
- `Entitlements/*`: the precedence rules, override history, downgrade read-only behaviour, cache-version invalidation.
- `Limits/ConcurrencyTest` (MySQL job): two parallel creates at limit − 1 → exactly one succeeds.
- `Platform/SupportSessionTest`: approval, expiry, revoke, audit of every request, deny list.
- `Integrity/QueriesTest`: the nightly integrity queries return no rows on seeded data.

**CI:** keep the SQLite suite and **add a MySQL job** that runs the migrations, integrity tests and concurrency tests. Today MySQL-specific behaviour is only checked by hand on staging (PO-21 [V]).

### Test actors (`tests/Support/Actors.php`)

| Helper | Builds |
|---|---|
| `platformOwner()`, `platformSupport()` | `platform_users` with platform roles |
| `company()` | A company built through `CompanyProvisioner`, with an active subscription on a plan with every feature |
| `companyOwner()`, `companyAdmin()`, `dispatcher()`, `officeUser()` | Users in a company with the matching system role |
| `driverUser()` | A driver with a linked user and the Driver role |
| `withPlan($features)`, `readOnly()`, `suspended()` | Company state modifiers |
| `actingAsTenant($user)` | Sets the context and Spatie team for the test |

## 6. Verification after every rebuild

These run automatically (`integrity:check`) and in `Integrity/QueriesTest`. Each query must return no rows.

```sql
-- 1. Roles never have a null team; guard matches team kind
SELECT id FROM roles WHERE team_id IS NULL OR ((guard_name = 'platform') <> (team_id = 0));

-- 2. Role pivots belong to the user's own company
SELECT m.model_id FROM model_has_roles m
JOIN users u ON m.model_type = 'App\\Models\\User' AND u.id = m.model_id
WHERE m.team_id <> u.company_id;

-- 3. Exactly the owner holds the Owner role
SELECT c.id FROM companies c
JOIN roles r ON r.team_id = c.id AND r.system_key = 'owner'
LEFT JOIN model_has_roles m ON m.role_id = r.id AND m.team_id = c.id
GROUP BY c.id, c.owner_user_id
HAVING COUNT(m.model_id) <> 1 OR MAX(m.model_id) <> c.owner_user_id;

-- 4. Every active company has the system roles, settings and sequence
SELECT c.id FROM companies c
LEFT JOIN company_settings s ON s.company_id = c.id
WHERE s.company_id IS NULL;

-- 5. Documents filed in an entity folder have a consistent subject (example for driver folders)
SELECT d.id FROM documents d JOIN folders f ON f.id = d.folder_id
WHERE f.driver_id IS NOT NULL AND (d.driver_id IS NULL OR d.driver_id <> f.driver_id);

-- 6. Storage counter matches stored files
SELECT c.id FROM companies c
LEFT JOIN (SELECT company_id, SUM(size_bytes) s FROM stored_files GROUP BY company_id) f ON f.company_id = c.id
WHERE c.storage_bytes <> COALESCE(f.s, 0);

-- 7. One open subscription per company (also enforced by the unique index)
SELECT company_id FROM subscriptions WHERE ended_at IS NULL GROUP BY company_id HAVING COUNT(*) > 1;

-- 8. External references point at existing rows (per local_type)
SELECT e.id FROM external_references e LEFT JOIN drivers d ON e.local_type = 'driver' AND d.id = e.local_id AND d.company_id = e.company_id
WHERE e.local_type = 'driver' AND d.id IS NULL;
```

The seed smoke check also asserts that:
- each demo company has its 7 system roles, a support user, settings, a sequence and an open subscription;
- the platform has at least two active Platform Owners.

## 7. If a staging database has data worth keeping

This isn't expected (decision log). If it does happen, rebuild into a **new** database and copy the data across with a one-off, dry-run-first command, `tenancy:import-legacy {source}`, following these rules:

| Legacy data | Rule |
|---|---|
| `companies.user_id` | Becomes `owner_user_id`. That user's `company_id` = this company. |
| A user who owns more than one company (`companies.user_id` isn't unique [V]) | **Not assigned automatically.** Listed for manual choice. |
| A user who owns no company and isn't super-admin (`user` role, or created by `UserController`) | **Not assigned automatically.** Listed. The product owner picks a company or drops the user. |
| Legacy roles (`super-admin`, `company`, `user`) and their assignments | **Not imported** ([06](06-roles-permissions.md) §10). Platform Owners are created fresh with `PlatformOwnerBootstrap`. Each imported company gets new roles from the templates, and its users are assigned to them by hand from the review report. |
| `subscriptions.user_id`, `payments.user_id` | Become the company the user owns. If the user owns no company or several, listed. |
| `users.stripe_id` | Becomes `companies.stripe_customer_id` for the owned company. Check the Stripe customer's metadata before linking. |
| `drivers.user_id` | Becomes `created_by_user_id` (the creator, not a login) |
| `drivers.status` | Mapping in [09](09-erd-audit.md) §3 |
| Asset-group text driver columns | `primary_*` are dropped. `second_driver_*` are listed (they can't be turned into ids safely). |
| Files | Copied into `companies/{id}/…` paths with `stored_files` rows, matching checksums. The source files are not deleted until verification passes. |
| SSNs stored before encryption | Re-run the removed `encrypt_driver_ssn` logic from git history (commit `ab77b52`) before the import |

**Nothing is ever assigned to a company by guessing.** Every row that can't be assigned goes to the review report and is left out until someone decides.

Verification compares row counts per company before and after, runs the integrity queries above, and checks file checksums.

## 8. Rollback and recovery

| Stage | Rollback |
|---|---|
| Before go-live (now) | Revert the commit range, then `migrate:fresh --seed`. Restore the backed-up dump and storage archive if the old demo state is needed. |
| After go-live | Every migration is additive and has a working `down()`. Risky changes use expand / contract: add the new column, write to both, backfill, switch reads, then drop the old column in a later release. |
| Feature switches | `features.availability = disabled` turns a module off for everyone without a deploy |
| Data issues | Point-in-time database backups and versioned storage (S3 versioning recommended [R]) |

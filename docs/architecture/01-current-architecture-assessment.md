# 01 · Current architecture assessment

This assessment covers what the application is today, at commit `d1d4d8d` (11 October 2026). Unless a line says otherwise, every finding is **[V]**, verified in the code.

## 1. Stack and shape

| Area | Today |
|---|---|
| Framework | Laravel 13, PHP 8.3+ (`composer.json`) |
| Database | MySQL in `.env` / `.env.example`. Tests run on SQLite `:memory:` (`phpunit.xml`). |
| Authorization | `spatie/laravel-permission` 8.x with **teams off** (`config/permission.php`). Three global roles: `super-admin`, `company`, `user` (`database/seeders/PermissionSeeder.php`). |
| Billing | `stripe/stripe-php` called directly, not Cashier (`app/Services/Stripe/*`) |
| UI | Blade, server-rendered tables via `yajra/laravel-datatables-oracle`, Toastr flash messages |
| Background work | `database` queue. Queued mailables and notifications. Scheduled jobs in `routes/console.php`: subscription expiry reminders at 09:00, compliance digest at 09:30, closing expired trials at 10:00, OTP pruning. |
| SMS | Vonage, used for applicant OTP and subscription-expiry SMS (`app/Services/OTPService.php`, `SmsService.php`) |
| Files | Private `local` disk for driver, compliance and service documents. Public disk for company logos, policy PDFs and site branding. |
| Routes | `routes/web.php` and `routes/auth.php` only. There is no `routes/api.php`. |
| Tests | Pest 4, 1,184 passing after the Laravel 13 upgrade (`docs/testing/PRODUCT_DECISIONS.md` rule 1.7). Module suites live in `tests/Feature/Modules/*`. |

## 2. Modules that exist

| Module | Main code | Notes |
|---|---|---|
| Registration and auth | `Auth/RegisteredUserController.php`, `Requests/Auth/LoginRequest.php` | Registration creates a user, a company and the `company` role in one transaction. The company is named after the person (`company_name => $request->name`, line 66). Email verification is required (PO-15). There is no 2FA and no social login. |
| Companies | `CompanyController.php` | The owner edits their own company (`ensureCanManageCompany`, lines 550-563). The super-admin lists, creates and deactivates companies. |
| Users and roles | `UserController.php`, `RoleController.php` | Super-admin only, and everything is global (see risks R1–R3). |
| Public driver application | `ApplicationFormController.php` | 10 steps, keyed by company slug, with OTP, resume, status check and withdraw. Tenant binding is careful (`applicationDriver()`, lines 1172-1197). |
| Drivers and DQ file | `DriverController.php`, `Services/Driver/*` | Wizard for the history tables and consents. Hire and reject through `DriverHireService`. |
| Driver compliance | `DriverDocumentUploadController.php`, `DriverComplianceDashboardController.php`, `Services/Compliance/DriverComplianceService.php` | Statuses are computed in SQL, 25 per page. |
| Fleet | `VehicleController.php`, `TrailerController.php`, `AssetGroupController.php` | Soft deletes and restore. VIN and unit number unique per company. |
| Fleet compliance | `DocumentUploadController.php`, `ComplianceDashboardController.php`, `FleetComplianceService.php` | Paginated, with SQL summaries (PO-01). |
| Maintenance | `ServiceLogController.php`, `MaintenanceScheduleController.php` | Date, mileage and engine-hour schedules (PO-04 to PO-07) |
| Reminders | `ComplianceReminderService.php`, `SendComplianceDigestReminders.php` | One email per driver per day |
| Billing | `CheckoutController`, `BillingController`, `StripeWebhookController`, `Admin/SubscriptionAdminController` | Every billing record belongs to a **user** (see risk R6) |
| Settings | `SiteSettingController`, `TawkToSettingController`, `DocumentTypeController` + lookup controllers | Global settings and lookups (LKP-01) |

## 3. Strengths to keep

- **Tenant checks in the existing tenant controllers are consistent.** Every record lookup in the driver, fleet, document, maintenance and asset-group controllers goes through `applyCompanyFilter` or `authorizeCompanyAccess`. Examples: `DriverController.php:289,369,394`, `DocumentUploadController.php:455-466`, `AssetGroupController.php:227`.
- **Child records are reached only through an authorized parent.** For example, licences and accidents through the driver (`DriverCrudService.php:96-118`), and service documents through the log (`ServiceLogController.php:610-621`).
- **The public application is tightly bound.** It checks the session driver, slug, company, `source` and `status` on every step (`ApplicationFormController.php:1172-1197`). Rate limits apply to OTP endpoints (`routes/web.php:60-146`).
- **Files are private by default.** Names come from `hashName()`, and responses send `nosniff` and `no-store`. Downloads authorize through the parent record.
- **SSN is encrypted** (`Driver.php` cast `encrypted`).
- **Compliance dashboards compute in SQL** and paginate (`DriverComplianceService.php:137-204`).
- **Billing is defensive.**
  - Webhook writes are idempotent by unique Stripe ids.
  - The success page checks ownership before syncing.
  - Events from the shared account are filtered by `app_host` (PO-18).
  - Database and PHP give the same answer on whether a subscription grants access (`AccessibleParityTest`).
- **Strong test culture.** There are module suites, tests that pin current behaviour, and a route guard matrix (`tests/Feature/Modules/AccessControl/RouteGuardMatrixTest.php`).

## 4. Risks and weaknesses

Ranked by impact on the multi-tenant target.

### Tenancy and authorization

| ID | Risk | Evidence | Effect |
|---|---|---|---|
| R1 | **Only an owner has a tenant.** `User::company()` is a `hasOne` on `companies.user_id`. Any other user resolves to no company and is filtered to `company_id = -1`. | `app/Models/User.php:54-57`, `app/Traits/CompanyFilterTrait.php:28-31,105-106` | A company cannot have staff or drivers who log in, so the client's requirements are blocked |
| R2 | **Roles are global.** A super-admin editing the `company` role changes every tenant. | `config/permission.php` (`teams` false), `RoleController.php:130-157`, `RoleManagementTest.php:63-72` | No per-company roles or permissions |
| R3 | **User management is global.** `UserController` lists every user and offers `Role::all()`. Users it creates get no company. | `UserController.php:19,44,54,73-78` | It is safe only because tenants lack `users.*`; one permission grant would expose every user |
| R4 | **The super-admin bypasses tenant isolation silently.** The trait returns unfiltered queries for super-admins. Product decisions PO-02, PO-07 and PO-08 build on this. | `CompanyFilterTrait.php:97,119`; `VehicleController.php:447-456`; `DashboardController.php:99` | No audit trail of platform staff reading tenant data |
| R5 | **Isolation is controller-only.** There are no global scopes and no policies. `DB::table`, `fromSub` and `unique:` / `exists:` rules are never scoped. | `DriverComplianceService.php:137-204`, `FleetComplianceService.php:34-113`, `DriverCrudService.php:175` | Every new query must remember to filter. New modules (trips, API) raise the risk. |
| R11 | **Company-level actions are only permission-gated.** `CompanyController::index` lists every company and `destroy` has no ownership check. | `CompanyController.php:25,356-392` | Safe only while the seeded `company` role lacks `companies.view` and `companies.delete` |
| R12 | **Tenant context state can leak.** The trait memoizes the tenant per controller instance (a cross-tenant leak under Octane). With teams on, Spatie's team id lives in a singleton that survives across queued jobs. | `CompanyFilterTrait.php:10-34`; `vendor/spatie/laravel-permission/src/PermissionRegistrar.php:105-113` | Must be handled by the request and job tenant middleware |

### Data and billing

| ID | Risk | Evidence | Effect |
|---|---|---|---|
| R6 | **Billing belongs to the user.** That covers the Stripe customer, checkout metadata, webhook resolution, trials and the `Subscribed` middleware. | `CheckoutService.php:27-33,125`; `WebhookProcessor.php:98-103,336-366`; `User.php:73-94`; `Subscribed.php:23` | Each user would need a subscription, and could take their own trial |
| R7 | **Cascading deletes can destroy a tenant.** `companies.user_id`, `drivers.user_id` (the creator!), `subscriptions.user_id` and `payments.user_id` all cascade. | The four create-table migrations | Deleting one user can erase a company's DQ files and financial history. PO-13 and PO-17 only avoid this in code. |
| R8 | **No plan limits, no feature catalogue.** `plans.features` is marketing JSON. Plans are duplicated per billing cycle. The landing page promises "Up to 10 drivers" and nothing enforces it. | `PlanSeeder.php:14-157`; `resources/views/welcome.blade.php:658-660` | Plan-based access can't be built without new tables |
| R9 | **Medical certificate and CDL expiry are not tracked for compliance.** The compliance engine reads only `document_types` rows. `drivers.medical_certificate_expiration_date` and `licenses.expires` are never checked. | `DriverComplianceService.php`; `DocumentTypeSeeder.php:17-29` | Two core DQ items can lapse silently. [L] 49 CFR 391.41, 391.51 |
| R10 | **Migrations that touched existing data were removed.** `d1d4d8d` deleted the migrations that moved files to the private disk and encrypted existing SSNs. | `git show --stat d1d4d8d` | Fine on a fresh database. Any older environment must be wiped, including `storage/app/public`. |

### Operations

| ID | Risk | Evidence | Effect |
|---|---|---|---|
| R13 | **Stripe webhooks run synchronously** inside the HTTP request, with no event-id store and no ordering guard | `StripeWebhookController.php:18-37`; `WebhookProcessor.php:60-68,373-384` | A stale `subscription.updated` can overwrite newer state |
| R14 | **Some scheduled work ignores company state.** The compliance digest emails drivers of inactive or lapsed companies. Expiry reminders go to `subscription->user`. | `SendComplianceDigestReminders.php:37-42`; `SendSubscriptionExpiryReminders.php:57,163` | Wrong recipients once billing moves to companies |

## 5. Smaller confirmed bugs

These are fixed during Phase 0; see [13-implementation-roadmap.md](13-implementation-roadmap.md).

- `CompanyController::store` creates the owner without assigning a role (`:136-187`). That owner gets 403 on every `permission:` route.
- `verified` is not enforced on the `/admin` group (`routes/web.php:214`).
- `DriverComplianceDashboardController.php:58-61,141-144` reads `phone`, `license_number` and `license_state`, which don't exist on `drivers`.
- `MaintenanceSchedule::serviceLogs()` uses `maintenance_schedule_id`, which `service_logs` doesn't have (`MaintenanceSchedule.php:78-81`).
- `MaintenanceSchedule::scopeForVehicle` uses `orWhereNull('vehicle_id')` with no company filter (`:238-242`). It is unused today.
- `DocumentType`, `VehicleDocument` and `TrailerDocument` have `deleted_at` columns but no `SoftDeletes` trait, so their deletes are hard deletes.
- `ServiceLogController:43` has an N+1 on `maintenanceCategories`.
- The drivers list loads every licence per row and sorts in PHP (`DriverController.php:35-37,82`).
- The admin subscription dashboard loads every subscription into memory (`SubscriptionAdminController.php:29`).
- `accessEndsAt()` prefers `trial_ends_at` in its COALESCE (`Subscription.php:79-84`). A `past_due` subscription that started as a trial therefore loses access early. [A] Inferred from the code; no test covers it.
- The `SubscriptionExpired` and `SubscriptionExpiringSoon` events are fired but have no listeners.

## 6. Documents that disagree with the code

| Claim | Source | Reality |
|---|---|---|
| "Login includes optional two-factor authentication" | Gap report §1, and `DriverFilesHub-Features-Workflow.pdf` | There is no 2FA. `UserSuspendRoutesTest.php:63-71` asserts the old 2FA route is gone. |
| "A profit page layout exists" | Gap report §8 | The old `/profit` page was an unrelated calculator, removed under ACL-04. `RouteGuardMatrixTest.php:210-229` asserts a 404. |
| "Set permissions per role: Available" | Gap report §1 | Only for the super-admin, and roles are global |
| Accident and fuel stop "Partial" | Gap report §7 | They rest on unrelated tables (the applicant's past accidents, the `fuel_types` lookup). They are missing. |
| "Notify drivers of new messages: Partial" | Gap report §10 | Drivers have no login, and only compliance emails exist |
| "Decisions the agent will defer" table | `docs/testing/MODULE_FIX_PROMPT.md:72-84` | Stale. All six are resolved (LKP-01, APP-07, CMP-05, AUTH-04, ACL-02, ACL-06). |
| "Stripe (Laravel Cashier)" | `.env.example:69` | Cashier is not installed |

## 7. What this means for the target

Today's tenant model is "the owner and their company". Every requirement the client added (staff users, driver logins, per-company roles, company billing, plan limits, mobile API) depends on replacing it. The replacement is designed in documents 03 to 08:

1. Tenant context taken from `users.company_id`.
2. Isolation enforced in several layers: scopes, policies, validation, jobs, files and cache.
3. A separate platform layer for the SaaS operator.
4. Spatie teams for per-company roles.
5. Billing and entitlements owned by the company.

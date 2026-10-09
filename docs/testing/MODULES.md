# Module Catalog: DriverFilesHub (driver-qualification)

This is the source of truth the `laravel-module-qa` agent reads before testing a module.
Each module is small enough to audit in one run (about 5–20 minutes) without touching the rest of the app.

**How to run one module**

| Where | Command |
|---|---|
| Claude Code (recommended) | `/test-module <slug>`, e.g. `/test-module asset-groups` |
| Any other chat/agent | Paste `docs/testing/MODULE_TEST_PROMPT.md` and change the `MODULE:` line |
| That module's tests only | `php artisan test tests/Feature/Modules/<Dir> tests/Unit/Modules/<Dir>` |
| Open bugs proven by tests | `composer test:known-issues` |
| Larastan, one module | `vendor/bin/phpstan analyse <scope paths> --memory-limit=1G` |

Reports land in `docs/testing/reports/<slug>.md`.

**Hotspot status legend**: `CONFIRMED` = already reproduced by a probe test during the initial audit (2026-10-08). `SUSPECTED` = found by reading code, still needs a test. The agent must turn every hotspot into a test, then confirm or dismiss it.

**Severity**: P0 critical, P1 high, P2 medium, P3 low. Full rubric is in `.claude/agents/laravel-module-qa.md`.

---

## Recommended run order (riskiest first)

| # | Slug | Module | Highest known | Test dir |
|---|---|---|---|---|
| 1 | `lookups` | Master data (types, groups, fuel, equipment, categories, document types) | **P0 confirmed** | `Lookups` |
| 2 | `users-roles` | Users, roles & permissions | **P0 confirmed** | `UsersRoles` |
| 3 | `public-application` | Public 10-step driver application | **P0 confirmed** | `PublicApplication` |
| 4 | `settings` | Site settings & Tawk.to | **P0 confirmed** | `Settings` |
| 5 | `asset-groups` | Asset groups (driver ↔ vehicle ↔ trailer) | **P0 confirmed** | `AssetGroups` |
| 6 | `access-control` | Cross-cutting: route guards, tenancy trait, deps, config | P1 | `AccessControl` |
| 7 | `drivers` | Driver CRUD + 10-step admin wizard | P1 | `Drivers` |
| 8 | `driver-compliance` | Driver compliance dashboard & documents | P1 | `DriverCompliance` |
| 9 | `fleet-compliance` | Vehicle/trailer compliance dashboard & documents | P1 | `FleetCompliance` |
| 10 | `maintenance` | Service logs & maintenance schedules | P1 | `Maintenance` |
| 11 | `fleet-assets` | Vehicles & trailers | P1 | `FleetAssets` |
| 12 | `otp-sms` | OTP, SMS, phone normalization | P1 | `OtpSms` |
| 13 | `companies` | Company accounts & policy PDFs | P1 | `Companies` |
| 14 | `billing` | Plans, Stripe checkout, portal, webhooks | P2 | `Billing` |
| 15 | `subscription-lifecycle` | `Subscribed` gate, expiry reminders, notifications | P2 | `SubscriptionLifecycle` |
| 16 | `subscription-admin` | Super-admin subscription & plan management | P2 | `SubscriptionAdmin` |
| 17 | `driver-hiring` | Hire / not-hire decision & emails | P2 | `DriverHiring` |
| 18 | `compliance-reminders` | Reminder endpoints & daily digest command | P2 | `ComplianceReminders` |
| 19 | `auth` | Login, registration, password, email verification, profile | P2 | `Auth` |
| 20 | `dashboard` | Dashboards & `/profit` | P2 | `Dashboard` |
| 21 | `mail-delivery` | Mail config, deliverability command, mail templates | P3 | `MailDelivery` |

Shared harness, used by every module (already built, don't rebuild): `tests/Support/Actors.php`, `tests/Support/Perf.php`, `tests/Support/StripeFake.php`, factories `Company`, `Driver`, `Vehicle`, `Trailer`, `Plan`, `Subscription`, `DocumentType`, `User` in `database/factories/`. Harness self-test: `tests/Feature/Modules/Harness`.

---

## 1. `lookups`: Master data
- **Scope**: `app/Http/Controllers/{VehicleType,VehicleGroup,FuelType,EquipmentType,MaintenanceCategory,DocumentType}Controller.php`, `app/Models/{VehicleType,VehicleGroup,FuelType,EquipmentType,MaintenanceCategory,DocumentType}.php`, `database/seeders/{VehicleTypes,VehicleGroups,FuelTypes,EquipmentTypes,MaintenaneCategoy,DocumentType}Seeder.php`, views `admin/{vehicle-type,vehicle-group,fuel-type,equipment-type,maintenance-category}`, `admin/settings/document-types`
- **Routes**: `admin.vehicle.type.*`, `admin.vehicle.group.*`, `admin.fuel.type.*`, `admin.equipment.type.*`, `admin.maintenance.category.*`, `admin.settings.document-types.*`. Middleware is `auth`, `Subscribed` only, with **no `permission:`**.
- **Permissions defined but unused**: `vehicle-types.*`, `vehicle-groups.*`, `fuel-types.*`, `equipment-types.*`, `maintenance-categories.*`, `document-types.*`
- **Note**: these tables are global (shared by all tenants). FKs `vehicles.{vehicle_type_id,vehicle_group_id,fuel_type_id}` and `trailers.{equipment_types_id,vehicle_group_id}` are `onDelete('cascade')`.

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| LKP-01 | P0 | FIXED | Any subscribed tenant can delete a global lookup. Deleting fuel type "Diesel" as tenant A **hard-deleted all of tenant B's vehicles** through the FK cascade, bypassing SoftDeletes. Same path likely for vehicle types/groups (vehicles+trailers) and equipment types (trailers). |
| LKP-02 | P1 | FIXED | Tenants can create/rename lookups seen by every tenant, plus stored XSS via `addslashes($row->name)` inside `onclick` (DataTables `action` column). |
| LKP-03 | P2 | FIXED | `MaintenanceCategoryController::destroy` calls undefined `MaintenanceCategory::maintenanceRecords()` → 500 (Larastan `method.notFound`). |
| LKP-04 | P2 | FIXED | Deleting/toggling a `DocumentType` silently changes compliance % for every tenant; no usage guard. |
| LKP-05 | P3 | FIXED | `EquipmentTypeController::destroy` usage check is commented out. |

## 2. `users-roles`: Users, roles & permissions
- **Scope**: `app/Http/Controllers/{UserController,RoleController}.php`, `database/seeders/PermissionSeeder.php`, `config/permission.php`, views `admin/users/**`, `admin/roles/**`
- **Routes**: `users.*` (prefix `/users`, middleware **`auth` only**), `admin.roles.*` (`permission:roles.*`)

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| USR-01 | P0 | FIXED | `/users/*` has only `auth`. A company tenant `PUT /users/{own id}` with `roles=[super-admin id]` and **became super-admin**. Any logged-in user can also list all users and change any user's email/password/roles. |
| USR-02 | P1 | FIXED | Routes `users.2fa.reset`, `users.suspend`, `users.unsuspend` point at methods that don't exist → 500. |
| USR-03 | P2 | FIXED | `RoleController::destroy` returns an empty response and lets anyone with `roles.delete` delete `super-admin`/`company` roles (lockout); `show()` is empty. |
| USR-04 | P1 | FIXED | `PermissionSeeder` truncates `users` + `companies` and creates `superadmin@gmail.com` / `12345678`. Destructive, and leaves default credentials if ever run in production. |
| USR-05 | P2 | FIXED | Setting a user `inactive` doesn't end existing sessions (status only checked at login). |
| USR-06 | P3 | FIXED | Role/permission exception messages are echoed to the UI via toastr. |

## 3. `public-application`: Public driver application (no auth)
- **Scope**: `app/Http/Controllers/ApplicationFormController.php`, `app/Http/Requests/PublicApplication/*`, `app/Services/Driver/{DriverCrudService,DriverDocumentWizardService}.php` (as used here), `app/Mail/ApplicationSubmittedMail.php`, views `application/**`
- **Routes**: `application.form`, `public.application.*` (prefix `/{slug}/application`). Middleware is `web` only, with **no throttle**.
- **Fakes**: bind a mocked `App\Services\OTPService` (or mock `Vonage\Client`), `Mail::fake()`, `Storage::fake('public')`

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| APP-01 | P0 | FIXED | `checkApplicationSession()` returns a redirect that **every caller ignores**. With no session, an anonymous POST to `store.step7` overwrote another applicant's FMCSA consent signature. All `step2..10` GET/POST and `withdraw/{driver_id}` are affected; GET pages render any driver's PII by id. |
| APP-02 | P0 | FIXED | Store requests validate `driver_id` with `exists:drivers,id` only, not bound to the session driver or the `{slug}` company. Cross-company writes remain possible after APP-01 is fixed unless both are enforced. |
| APP-03 | P1 | FIXED | `verifyResume` (POST `/resume`) grants a full application session with only phone + date of birth, **no OTP**. |
| APP-04 | P1 | FIXED | No rate limiting on `send-otp`, `resend-otp`, `check-resume`, `check-status`, `verify-otp`. `resendOtp` accepts any `phone` from the request → SMS pumping / toll fraud; phone+DOB enumeration. |
| APP-05 | P1 | FIXED | `checkResumePhone` / `checkStatus` reveal whether a phone (+DOB) has an application at a company. |
| APP-06 | P2 | FIXED | `withdraw` writes column `withdrawn_at` (doesn't exist) and status `withdrawn` (not in the enum) → always fails. |
| APP-07 | P2 | FIXED | `drivers.email` is globally unique → someone who applied to company A can't apply to company B. |
| APP-08 | P2 | FIXED | Only `show`/`start` check `company.status = active`; inactive companies still accept POSTs. |
| APP-09 | P2 | FIXED | `application_session_token` is generated but never verified; `saveProgress` returns an empty 200; `getOtpFromRequest` unused. |
| APP-10 | P1 | FIXED | Found during the module run: public uploads (licence, medical card, forfeiture, photo) were stored on the public disk under the **client's** file extension, so a valid PNG named `x.html`/`x.svg` was served as HTML (stored XSS). Photo names also collided within the same second. |

## 4. `settings`: Site settings & Tawk.to
- **Scope**: `app/Http/Controllers/{SiteSettingController,TawkToSettingController}.php`, `app/Models/SiteSetting.php`, `app/Helpers/settings.php`, views `admin/settings/{site,tawk}/**`, `partials/tawk-widget.blade.php`, GA snippet in `layouts/main-layout.blade.php` + `welcome.blade.php`
- **Routes**: `admin.settings.site.*` (**no `permission:`**), `admin.settings.tawk.*` (`settings.view|edit`)
- **Existing tests**: `tests/Feature/TawkToSettingTest.php`

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| SET-01 | P0 | FIXED | Any subscribed tenant can `PUT /admin/settings/site`: changed the global site name, and `logo` has **no validation** (`'logo' => 'nullable'`). `shell.php` was stored as `settings/<hash>.php` on the public disk. The root `.htaccess` maps `.php` to the PHP handler, so `/storage/settings/<hash>.php` is likely **remote code execution**. |
| SET-02 | P1 | FIXED | Tenants can rewrite global meta tags, contact info and the Google Analytics ID shown on every page. |
| SET-03 | P2 | DISMISSED | Favicon allows SVG (stored XSS when opened directly from `/storage`). |
| SET-04 | P3 | FIXED | GA ID is HTML-escaped but placed in a JS string; enforce `^G-[A-Z0-9]+$`. Check Tawk ID extraction regex & encrypted cast round-trip. |

## 5. `asset-groups`: Asset groups
- **Scope**: `app/Http/Controllers/AssetGroupController.php`, `app/Models/AssetGroup.php`, views `admin/asset-group/**`
- **Routes**: `admin.asset-group.*`. Middleware is `auth`, `Subscribed`, with **no `permission:`** (`asset-groups.*` defined but unused). Note the odd duplicate route `asset-group/asset-group/get-dropdown-data`.
- **Factories to add**: `AssetGroupFactory`

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| AGR-01 | P0 | CONFIRMED | No tenant scoping at all. Tenant A `GET edit/{id}` of tenant B's group → 200; `index`, `update`, `destroy`, `restore` are likewise unscoped. |
| AGR-02 | P0 | SUSPECTED | `index` and `getDropdownData` expose every company's vehicles, trailers and active drivers (the company-filtered `$drivers` is overwritten by `Driver::where('status','active')->get()`). |
| AGR-03 | P1 | SUSPECTED | `store`/`update` accept another tenant's `driver_id`/`vehicle_id`/`trailer_id` (`exists:` only). |
| AGR-04 | P1 | SUSPECTED | Stored XSS: `group_name` in raw DataTables columns and `addslashes` `onclick`. |
| AGR-05 | P2 | SUSPECTED | `group_name` globally unique (cross-tenant collision & existence leak). |

## 6. `access-control`: Cross-cutting
- **Scope**: `routes/web.php`, `routes/auth.php`, `routes/console.php`, `bootstrap/app.php`, `app/Http/Middleware/*`, `app/Traits/{CompanyFilterTrait,HasSubscription}.php`, `config/{permission,session,app}.php`, `.htaccess`, `.env.example`, `composer.json/lock`
- **Deliverables specific to this module**: a **route guard matrix test** that iterates `Route::getRoutes()` and asserts every non-public route has `auth`, every `/admin` route has `Subscribed` or `role:super-admin`, and lists routes with no `permission:`. Also unit tests for `CompanyFilterTrait`, `composer audit`, and arch tests (`arch()->expect('App')->not->toUse(['dd','dump','ray'])`, no `env()` outside `config/`).

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| ACL-01 | P1 | SUSPECTED | Admin routes with no `permission:` middleware: vehicle-type, vehicle-group, fuel-type, equipment-type, maintenance-category, asset-group, service-log, maintenance-schedule, settings/site, settings/document-types, plus `/users` with no role at all. Self-registration + free trial = anyone can reach them. |
| ACL-02 | P1 | SUSPECTED | `composer audit`: 49 advisories in 13 packages (incl. high: `laravel/framework`, `guzzlehttp/guzzle`, `symfony/http-kernel`, `symfony/mime`, `league/commonmark`). |
| ACL-03 | P1 | SUSPECTED | No `throttle` on any public/OTP route (see APP-04). |
| ACL-04 | P2 | CONFIRMED | `GET /profit` has no middleware (guest got 200). |
| ACL-05 | P2 | SUSPECTED | `getAllUserCompanyId()` returns the super-admin's own company → records a super-admin creates for a tenant are attributed to the super-admin's company. |
| ACL-06 | P2 | SUSPECTED | Root `.htaccess` redirects port-80 traffic to `http://127.0.0.1:8000` and has unreachable rules; `.env` has `APP_DEBUG=true`, `APP_ENV=local`. Check what production uses. |
| ACL-07 | P3 | SUSPECTED | Dead/contradictory code: `BlockExpiredLogin` (unregistered, uses nonexistent `$user->subscription`), `HasSubscription` trait (unused, conflicts with `User::activeSubscription()`), `CheckApplicationSession` imported in `bootstrap/app.php` but missing. |
| ACL-08 | P3 | SUSPECTED | `env()` in Blade (`main-layout`, `welcome`) returns null under `config:cache`. |
| ACL-09 | P2 | SUSPECTED | Super-admin detection is by role *name* `'super-admin'` everywhere; renaming/deleting the role (USR-03) breaks access control. |

## 7. `drivers`: Driver management (admin)
- **Scope**: `app/Http/Controllers/DriverController.php` (all except `updateHireStatus`), `app/Services/Driver/{DriverCrudService,DriverDocumentWizardService}.php`, `app/Http/Requests/Driver/*`, models `Driver, DriverDocument, License, Experience, Accident, Violation, Forfeitures, EmploymentRecord, ResidenceAddress, Country, State`, views `admin/driver/**`
- **Routes**: `admin.driver.*`, `admin.drivers.get-driver-details` (`permission:drivers.*`)

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| DRV-01 | P1 | SUSPECTED | Stored XSS: driver name in `onclick="deleteDriver(id, '...')"` escaped with `addslashes` only (HTML attribute context). Public applicants control the name, so a script runs in the admin's session. |
| DRV-02 | P1 | SUSPECTED | SSN stored in plaintext (`drivers.ssn` string, no `encrypted` cast). |
| DRV-03 | P1 | SUSPECTED | Photos, license front/back, medical card, forfeiture docs on the **public** disk with predictable names (`driver_photo_<time>.<ext>`, `license_front_<time>_<uniqid>.<ext>`). Readable without auth at `/storage/...`. |
| DRV-04 | P2 | SUSPECTED | Photo rule allows `svg`; stored name uses client extension → stored XSS via SVG. |
| DRV-05 | P2 | SUSPECTED | `driver_photo_<time()>` collides for two uploads in the same second → one driver's photo replaces another's. |
| DRV-06 | P2 | SUSPECTED | `UpdateDriverRequest` lets a tenant set any status (incl. `active`), bypassing the hire workflow, its emails and audit fields. `updateStatus` uses a different status set (`submitted/under_review/approved`). |
| DRV-07 | P2 | SUSPECTED | DataTables `order[0][dir]` passed raw to `orderBy` → invalid value = 500. |
| DRV-08 | P2 | SUSPECTED | `update()` deletes and re-inserts every child row (residences, experiences, accidents, violations, forfeitures, employment) on each save; `destroy` hard-deletes and orphans files; exception text returned to client. |
| DRV-09 | P3 | SUSPECTED | `loadWizardDriver(int)` receives route strings → non-numeric id = TypeError 500, not 404. `edit()` crashes if no `US` country row. |
| DRV-10 | P2 | SUSPECTED | Perf: `index` runs 6 COUNTs + DataTables + `whereHas(company)` search; add a query budget. |

## 8. `driver-compliance`: Driver compliance
- **Scope**: `app/Http/Controllers/{DriverComplianceDashboardController,DriverDocumentUploadController}.php`, `app/Services/Compliance/DriverComplianceService.php`, `app/Models/DriverComplianceDocument.php`, views `admin/compliance/drivers*`
- **Routes**: `admin.compliance.drivers*`, `admin.compliance.driver.*` (`permission:drivers.dashboard|edit`)

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| DCMP-01 | P1 | SUSPECTED | Documents stored on the public disk as `documents/drivers/<time>_<original name>`: guessable, so the authorized view/download routes are bypassable. |
| DCMP-02 | P1 | SUSPECTED | `upload_to_all` stores ONE file shared by every driver; replacing/deleting one driver's document deletes the file for all. |
| DCMP-03 | P2 | SUSPECTED | `DriverComplianceService`: expiring docs aren't counted compliant, so percentage < 100 and status is always `danger`. The `warning` branch is unreachable. |
| DCMP-04 | P2 | SUSPECTED | Stored filename keeps the client name/extension (`x.html` with PDF bytes passes `mimes:pdf`) → stored XSS from `/storage`. |
| DCMP-05 | P2 | SUSPECTED | Perf: dashboard loads all active drivers + all docs unpaginated; driver docs allow past `expiry_date` while fleet docs require `after_or_equal:today`. |
| DCMP-06 | P3 | SUSPECTED | Exception messages returned in JSON. |

## 9. `fleet-compliance`: Vehicle/trailer compliance
- **Scope**: `app/Http/Controllers/{ComplianceDashboardController,DocumentUploadController}.php`, `app/Models/{VehicleDocument,TrailerDocument}.php`, views `admin/compliance/fleet*`
- **Routes**: `admin.compliance.fleet`, `admin.compliance.{vehicles,trailers}.*`, `admin.compliance.documents.*` (`permission:fleets.dashboard|edit`)

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| FCMP-01 | P1 | SUSPECTED | Same shared-file bug as DCMP-02 for `upload_to_all`. |
| FCMP-02 | P1 | SUSPECTED | Same public-disk / guessable filename issue as DCMP-01. |
| FCMP-03 | P2 | SUSPECTED | N+1: `getVehiclesList`/`getTrailersList` run one `exists` query per asset; `fleet()` loads every asset + docs unpaginated. |
| FCMP-04 | P2 | SUSPECTED | Controller re-implements compliance math; check it for the same `warning`-unreachable bug as DCMP-03. Any `assetType` other than `vehicle` falls into the trailer branch. |
| FCMP-05 | P3 | SUSPECTED | Exception messages returned in JSON. |

## 10. `maintenance`: Service logs & schedules
- **Scope**: `app/Http/Controllers/{ServiceLogController,MaintenanceScheduleController}.php`, `app/Models/{ServiceLog,ServiceDocument,MaintenanceSchedule}.php`, views `admin/{service-log,maintenance-schedule}/**`
- **Routes**: `admin.service-log.*`, `admin.maintenance-schedule.*`, with **no `permission:`** (`maintenance.*`, `scheduled.*` defined but unused)
- **Factories to add**: `ServiceLogFactory`, `MaintenanceScheduleFactory`, `MaintenanceCategoryFactory`

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| MNT-01 | P1 | SUSPECTED | `store`/`update` validate `vehicle_id` with bare `exists:vehicles,id` → a tenant can attach logs/schedules to another company's vehicle. |
| MNT-02 | P1 | SUSPECTED | Stored XSS through raw DataTables columns (vehicle info, notes, category names). |
| MNT-03 | P2 | SUSPECTED | Super-admin-created logs/schedules are saved with the super-admin's own `company_id`, not the vehicle's. |
| MNT-04 | P2 | SUSPECTED | `markAsCompleted` reads `$vehicle->engine_hours` (no such column) → always 0. Unit-test `MaintenanceSchedule::calculateNextDue()` (month ends, null intervals, each `schedule_type`). |
| MNT-05 | P2 | SUSPECTED | Service documents on the public disk; client extension kept; `.doc/.docx` allowed. |

## 11. `fleet-assets`: Vehicles & trailers
- **Scope**: `app/Http/Controllers/{VehicleController,TrailerController}.php`, `app/Models/{Vehicle,Trailer}.php`, views `admin/{vehicle,trailer}/**`
- **Routes**: `admin.vehicle.*`, `admin.vehicles.*`, `admin.trailer.*`, `admin.trailers.*` (`permission:vehicles.*|trailers.*`)

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| FLT-01 | P1 | SUSPECTED | Stored XSS: `unit_no`, `make`, `model`, `vin`, type/group names concatenated into raw DataTables columns. A tenant can target the super-admin, who sees every tenant's rows. |
| FLT-02 | P2 | SUSPECTED | `unit_no` and `vin` unique across all tenants → tenant B blocked by tenant A's data; the error leaks existence. |
| FLT-03 | P2 | SUSPECTED | `update($request->all())`: verify `$fillable` blocks everything sensitive; super-admin can move assets between companies without moving docs/groups. |
| FLT-04 | P3 | SUSPECTED | Index query lacks `withTrashed()`, so the restore button never renders. |

## 12. `otp-sms`: OTP, SMS & phone numbers
- **Scope**: `app/Services/{OTPService,SmsService,PhoneNumberService}.php`, `app/Jobs/SendSmsJob.php`, `app/Models/OtpVerification.php`, `app/Exceptions/Sms/SmsException.php`, Vonage binding in `app/Providers/AppServiceProvider.php`
- **Existing tests**: `tests/Feature/OTPServiceTest.php`, `tests/Feature/PhoneNumberServiceTest.php`
- **Fakes**: mock `Vonage\Client` (`verify()`, `messages()`), `Queue::fake()`

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| OTP-01 | P1 | SUSPECTED | `PhoneNumberService::isValid()` accepts any `+<country>` E.164 number, including premium-rate destinations. Combined with APP-04 that means toll fraud. |
| OTP-02 | P2 | SUSPECTED | Limits are per phone only (3/hour, 60 s resend); there's no per-IP or global cap. |
| OTP-03 | P2 | SUSPECTED | The legacy hashed-OTP path has no attempt counter (6-digit brute force); a failed Vonage check doesn't invalidate the record. |
| OTP-04 | P3 | SUSPECTED | `otp_attempts:*` cache key is never written; `generateOtpCode` unused; `OtpVerification::prunable()` without the `Prunable` trait never runs. |
| OTP-05 | P3 | SUSPECTED | `SendSmsJob` logs the full phone number while `SmsService` masks it. |

## 13. `companies`: Company accounts & policy PDFs
- **Scope**: `app/Http/Controllers/CompanyController.php`, `app/Models/{Company,PolicyPdf}.php`, views `admin/settings/company/**`
- **Routes**: `admin.settings.company*`, `admin.settings.policy.pdf*` (`permission:companies.*|policy-pdf.*`)

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| CMP-01 | P1 | SUSPECTED | Stored XSS: `company_name` in the logo `alt` attribute (raw column), slug inside `onclick`. |
| CMP-02 | P2 | SUSPECTED | A company owner can change their own `status` and email through `update`. |
| CMP-03 | P2 | SUSPECTED | `destroy` hard-deletes the company and its owner user → FK cascade wipes all drivers/vehicles; files left on disk. |
| CMP-04 | P2 | SUSPECTED | Logo allows SVG; `companies.slug` isn't unique, so duplicate slugs route `/{slug}/apply` to the wrong company. |
| CMP-05 | P3 | SUSPECTED | `PolicyPdf` is global (`PolicyPdf::first()`), not per company. Confirm against requirements. Raw exception shown on upload failure. |

## 14. `billing`: Plans, checkout, portal, webhooks
- **Scope**: `app/Http/Controllers/{PlanController,CheckoutController,BillingController,StripeWebhookController}.php`, `app/Services/Stripe/*`, `app/Services/Billing/TrialActivationService.php`, `app/Models/{Plan,Payment,Subscription}.php`, `app/Console/Commands/SyncPaymentsFromStripe.php`, views `billing/**`, plans on `welcome.blade.php`
- **Routes**: `stripe.webhook` (public, CSRF-exempt), `billing.*`, `pricing.plans`, `checkout*`, legacy `/subscription/*`
- **Fakes**: `Tests\Support\StripeFake::bindClient()` and `::signature()`. Never call real Stripe.

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| BIL-01 | P2 | SUSPECTED | No webhook event-id idempotency. Concurrent `checkout.session.completed` + `customer.subscription.created` can create two Subscription rows (no unique constraint) and two "activated" emails. |
| BIL-02 | P2 | SUSPECTED | `Subscription::isAccessible()` and `scopeAccessible()` disagree (e.g. `past_due`). Write parity tests. |
| BIL-03 | P2 | SUSPECTED | Trial abuse: `checkout` needs only `auth` (not `verified`), and trial use is per user, so a new account means a new trial. |
| BIL-04 | P2 | SUSPECTED | `CheckoutController::success` with another user's `session_id` must not sync. Verify the metadata ownership check. |
| BIL-05 | P3 | SUSPECTED | `User::activeSubscription()` loads all subscriptions on every call (the `Subscribed` middleware calls it per request). `StripeClientFactory` keeps a static client. |

## 15. `subscription-lifecycle`: Access gate, reminders & notifications
- **Scope**: `app/Http/Middleware/Subscribed.php`, `app/Console/Commands/SendSubscriptionExpiryReminders.php`, `app/Services/Billing/SubscriptionNotificationService.php`, `app/Notifications/*`, `app/Events/*`, `app/Models/SubscriptionNotificationLog.php`, schedule in `routes/console.php`
- **Fakes**: `Notification::fake()`, mock `Vonage\Client`, `$this->travelTo()`

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| SUB-01 | P2 | SUSPECTED | The log row is written before `notify()`; if sending throws, the reminder is never retried. |
| SUB-02 | P2 | SUSPECTED | Reminders fire only on an exact day match (`$days === $window`); a missed scheduler day skips that reminder forever. |
| SUB-03 | P2 | SUSPECTED | Expiry SMS goes to `company.phone` (free text, unvalidated); check failure handling. |
| SUB-04 | P2 | SUSPECTED | `/dashboard` (auth+verified) shows tenant data without `Subscribed`; `/admin/dashboard` requires it. |

## 16. `subscription-admin`: Super-admin billing tools
- **Scope**: `app/Http/Controllers/Admin/SubscriptionAdminController.php`, views `admin/subscriptions/**`, `admin/plans/**`
- **Routes**: `admin.subscriptions.*`, `admin.plans.*` (`role:super-admin`)

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| SADM-01 | P1 | SUSPECTED | Verify every route returns 403 for a tenant and a plain user (role middleware). |
| SADM-02 | P2 | SUSPECTED | `admin/plans/{create,edit}.blade.php` print `{!! json_encode(...) !!}` inside `<script>`; a feature containing `</script>` breaks out. |
| SADM-03 | P2 | SUSPECTED | `expire`/`suspend`/`reactivate` on admin/trial subscriptions (no Stripe id) must not call Stripe; `grant` while a sub is active. |

## 17. `driver-hiring`: Hire / not-hire
- **Scope**: `DriverController::updateHireStatus`, `app/Services/Driver/DriverHireService.php`, `app/Mail/{DriverHiredMail,DriverRejectedMail}.php`, views `emails/*hired*`, `emails/*rejected*`
- **Routes**: `admin.driver.hire-status` (`permission:drivers.hire`)

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| HIRE-01 | P2 | SUSPECTED | Status check happens outside the transaction with no row lock → double-submit sends two emails / flips state. |
| HIRE-02 | P2 | SUSPECTED | Validator reasons vs the `drivers.rejection_reason` DB enum must match exactly. |
| HIRE-03 | P1 | SUSPECTED | Cross-tenant hire/reject must be 403 (route-model binding + `authorizeCompanyAccess`). |

## 18. `compliance-reminders`: Reminders & digest
- **Scope**: `app/Http/Controllers/ComplianceReminderController.php`, `app/Services/ComplianceReminderService.php`, `app/Console/Commands/SendComplianceDigestReminders.php`, `app/Mail/{DriverComplianceReminderMail,VehicleComplianceStatusReminderMail,DriverComplianceDigestMail}.php`
- **Existing tests**: `tests/Feature/ComplianceReminderTest.php`. **4 tests fail today** (routes now need a permission + subscription); repair them with `Tests\Support\Actors`.

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| REM-01 | P2 | CONFIRMED | 4 existing tests fail against current routing. |
| REM-02 | P2 | SUSPECTED | No throttle on the send-reminder endpoints → inbox spamming. |
| REM-03 | P3 | SUSPECTED | Exception messages in JSON; digest de-dupe relies on `Cache::add` (fine on the database store, fragile on file/array). |

## 19. `auth`: Authentication, registration & profile
- **Scope**: `routes/auth.php`, `app/Http/Controllers/Auth/*`, `app/Http/Requests/Auth/LoginRequest.php`, `app/Http/Controllers/ProfileController.php`, `app/Http/Requests/ProfileUpdateRequest.php`, views `auth/**`, `profile/**`
- **Existing tests**: `tests/Feature/Auth/*`, `tests/Feature/ProfileTest.php`. **6 tests fail today**: registration needs the `company` role seeded; profile moved to `/admin/profile` behind `Subscribed`.

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| AUTH-01 | P2 | CONFIRMED | 6 existing tests fail against current code. |
| AUTH-02 | P2 | SUSPECTED | Login reveals "account is inactive" for existing emails (user enumeration). |
| AUTH-03 | P2 | SUSPECTED | Registration swallows every exception into a generic toast; a missing role silently rolls back the signup. |
| AUTH-04 | P2 | SUSPECTED | Deleting your profile cascades through Company → drivers/vehicles (FK cascade); confirm intended. |
| AUTH-05 | P3 | SUSPECTED | Company slug is derived from the person's name plus 4 random chars, not unique in the DB. |

## 20. `dashboard`: Dashboards
- **Scope**: `app/Http/Controllers/DashboardController.php`, views `dashboard.blade.php`, `admin/profit.blade.php`
- **Routes**: `dashboard` (auth, verified), `admin.dashboard` (auth, Subscribed), `admin.profit` (**none**)

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| DSH-01 | P2 | CONFIRMED | `/profit` is public. Check what the view exposes. |
| DSH-02 | P2 | SUSPECTED | About 20 COUNT queries per load, plus `whereHas` on doc tables without an `expiry_date` index. Add a query budget test with realistic data. |
| DSH-03 | P3 | SUSPECTED | Draft drivers are counted on the dashboard but hidden from tenants' driver list (numbers don't match). |

## 21. `mail-delivery`: Mail infrastructure
- **Scope**: `app/Console/Commands/CheckMailDeliverability.php`, `config/mail.php`, `AppServiceProvider::boot` (reply-to), all `app/Mail/*` envelopes/content, views `emails/**`
- **Existing tests**: `tests/Feature/MailDeliverabilityTest.php`, `tests/Feature/EmailNotificationTest.php`

| ID | Sev | Status | Hotspot |
|---|---|---|---|
| MAIL-01 | P2 | SUSPECTED | Mail bodies include tenant/applicant-controlled strings (names, company names); verify escaping in every template. |
| MAIL-02 | P3 | SUSPECTED | All mailables implement `ShouldQueue` (verified); check `tries`/`backoff` and the failure path, since a failed queued mail is silent. |

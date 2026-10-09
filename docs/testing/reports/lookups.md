# Module report: `lookups` (master data)

Run date: 2026-10-09. Tests: `tests/Feature/Modules/Lookups` (54 tests, all passing).

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| LKP-01 | P0 | FIXED | `LookupAuthorizationTest.php::LKP-01 → it forbids a tenant from deleting a lookup` (×6 lookups); `LookupAuthorizationTest.php::LKP-01 → it does not wipe another tenant's vehicles through the fuel type cascade`; `LookupDeleteGuardTest.php::LKP-01 → it refuses and keeps every tenant vehicle` (fuel type, vehicle type, vehicle group); `…::it counts soft-deleted vehicles too, since the cascade would hard-delete them`; `…::it refuses to delete a vehicle group used only by a trailer` | `routes/web.php`, `VehicleTypeController.php`, `VehicleGroupController.php`, `FuelTypeController.php`, `app/Models/FuelType.php` |
| LKP-02 | P1 | FIXED | `LookupAuthorizationTest.php::LKP-02 → it forbids a tenant from creating a lookup` (×6); `…::it forbids a tenant from renaming a lookup` (×6); `…::it still lets a tenant view the lookup list` (×6, regression guard); `LookupXssTest.php::it renders lookup names in the action column as escaped data attributes` (5 lookups × 2 payloads) | `routes/web.php`, all 5 named-lookup controllers, `resources/views/admin/{vehicle-type,vehicle-group,fuel-type,equipment-type,maintenance-category}/index.blade.php`, `DocumentTypeController.php` (`e()` on raw `module` column) |
| LKP-03 | P2 | FIXED | `LookupDeleteGuardTest.php::LKP-03 → it deletes an unused category instead of failing with a 500`; `…::it refuses to delete a category linked to a service log`; `…::it refuses to delete a category used by a maintenance schedule` | `MaintenanceCategoryController.php`, `app/Models/MaintenanceCategory.php` |
| LKP-04 | P2 | FIXED (one follow-up question deferred, see below) | `LookupAuthorizationTest.php::LKP-04 → it forbids a tenant from toggling a document type`; `LookupDeleteGuardTest.php::LKP-04 → it refuses and keeps the uploaded documents` (driver compliance, vehicle, trailer documents) | `routes/web.php`, `DocumentTypeController.php`, `app/Models/DocumentType.php` |
| LKP-05 | P3 | FIXED | `LookupDeleteGuardTest.php::LKP-05 → it refuses and keeps the trailer` | `EquipmentTypeController.php` |
| (all) | n/a | regression guard | `LookupAuthorizationTest.php::super-admin keeps full control → it lets a super-admin create, rename and delete an unused lookup` (×6) | n/a |

### What changed

- **Authorization (LKP-01, LKP-02, LKP-04).** Every write route (`create`, `store`, `edit`, `update`, `destroy`, `toggle-status`) for the six lookups now requires `permission:<module>.<create|edit|delete>`. These permissions are already defined in `PermissionSeeder`, and only `super-admin` has them. Read routes (`index`, document-type `show`, `by-module`) are unchanged, so tenants keep read access.
- **Cascade guard (LKP-01, LKP-03, LKP-04, LKP-05).** The FKs that point at lookups are `onDelete('cascade')`. Even a super-admin used to be able to hard-delete every tenant's vehicles, trailers, maintenance schedules or uploaded documents by deleting one lookup. `destroy` now returns `400 {success:false, message}` when the lookup is still referenced. Soft-deleted vehicles, trailers, service logs and schedules count as references, because the cascade would hard-delete them too. The guard runs before `DB::beginTransaction()`, so the early return no longer leaves a transaction open. That also fixes the old (unreachable) maintenance-category guard.
  - Vehicle type → `vehicles`. Vehicle group → `vehicles` + `trailers`. Fuel type → `vehicles` (new `FuelType::vehicles()` relation). Equipment type → `trailers` (replaces the commented-out check).
  - Maintenance category → `service_log_category` + `maintenance_schedules` (new `MaintenanceCategory::maintenanceSchedules()`). The call to the undefined `maintenanceRecords()` is removed. It threw inside the `try`, so every delete returned 500.
  - Document type → `driver_compliance_documents`, `vehicle_documents`, `trailer_documents` (new `DocumentType::driverComplianceDocuments()`). The message tells the admin to deactivate the type instead.
- **XSS (LKP-02).** The delete buttons no longer use `onclick="deleteX(id, '<addslashes(name)>')"`. They carry `data-action="delete" data-id="…" data-name="<e(name)>"`, and each index view has one delegated jQuery listener that calls the existing `deleteX(id, name)`. The confirm dialog already uses SweetAlert's `text:` (not `html:`), so the name stays inert. The `name` data column itself was already escaped by Yajra's default `escape => '*'`.

## Out-of-scope changes

- `routes/web.php`: added `->middleware('permission:…')` to the 25 lookup write routes listed above. No other route changed.

## Deferred items (questions for the product owner)

Both were answered on 2026-10-09 and implemented. See "Follow-up" below.

1. ~~FK cascade on lookups~~: **RESTRICT everywhere.**
2. ~~Global vs per-tenant document types~~: **keep the global list and let each company opt out.**

## Manual steps

Run `php artisan migrate` on deploy. It adds two migrations:

- `2026_10_09_000000_restrict_lookup_foreign_keys`: drops and re-creates 10 foreign keys with `ON DELETE RESTRICT`. On MySQL each `ALTER TABLE` rebuilds the FK, so run it in a quiet window on large `vehicles`/`trailers` tables. `down()` restores `CASCADE`. This was verified on SQLite (migrate, inspect `pragma_foreign_key_list`, roll back), but **not on MySQL**: run it on staging first.
- `2026_10_09_000100_create_company_document_type_table`: a new, empty table. No data changes.

No permissions changed. The new route uses `companies.edit`, which the `company` role already has.

## Follow-up: database-level delete protection and per-company document types

| Item | Result | Test file::test name | Files changed |
|---|---|---|---|
| FKs on lookups → RESTRICT | DONE | `LookupForeignKeyTest.php::it refuses at the database level to delete a lookup that is still referenced` (×10 links); `…::it still deletes a lookup nothing references` | `database/migrations/2026_10_09_000000_restrict_lookup_foreign_keys.php` |
| Per-company opt-out: toggle | DONE | `DocumentTypeCompanyOptOutTest.php::switching a type off → it lets a tenant switch a global type off and back on for their own company only`; `…::it returns 404 for an unknown document type`; `…::it forbids a user without companies.edit`; `…::it shows the My Company column to tenants only`; `…::it shows a tenant which types are switched off for their company` | migration `2026_10_09_000100_create_company_document_type_table.php`, `DocumentTypeController.php` (`toggleForCompany`, `company_enabled` column, admin buttons shown only with `document-types.edit/delete`), `resources/views/admin/settings/document-types/index.blade.php`, `routes/web.php`, `app/Models/{Company,DocumentType}.php` |
| Per-company opt-out: compliance | DONE | `DocumentTypeCompanyOptOutTest.php::compliance respects the company opt-out → …` (driver service, other companies unchanged, driver dashboard, fleet dashboard, vehicle details modal, vehicle accessors, daily digest) | `DriverComplianceService.php`, `DriverComplianceDashboardController.php`, `ComplianceDashboardController.php`, `SendComplianceDigestReminders.php`, `app/Models/{Vehicle,Trailer}.php` |

How it works:

- **RESTRICT:** these 10 links now use `ON DELETE RESTRICT`: `vehicles.{vehicle_type_id,vehicle_group_id,fuel_type_id}`, `trailers.{equipment_types_id,vehicle_group_id}`, `maintenance_schedules.maintenance_category_id`, `service_log_category.maintenance_category_id`, and `{driver_compliance,vehicle,trailer}_documents.document_type_id`. The app's in-use guards still return a friendly 400 first. The database constraint is the backstop for raw SQL or any future code path.
- **Opt-out storage:** `company_document_type` holds one row per (company, document type) that the company has switched off. The row cascades away if the company or the (unused) type is deleted.
- **Toggling:** tenants open Document Types (from Driver Compliance → Document Types) and see a **My Company** On/Off column with a Switch off / Switch on button (`POST admin/settings/document-types/{id}/company-toggle`, `permission:companies.edit`). This never changes the global `status`. Super-admins see the page as before.
- **Effect on compliance:** `DocumentType::scopeEnabledForCompany($companyId)` (active and not switched off) is used for single records: driver service, vehicle and trailer details modals, `Vehicle`/`Trailer` accessors. List views (driver and fleet dashboards, daily digest) load the opt-outs once with `DocumentType::disabledIdsByCompany()` and filter per record by **that record's own company**, so a super-admin viewing several companies sees each company's rules. Queries don't grow with the number of rows.
- **Not filtered:** uploading a document of a switched-off type and sending a manual reminder for one still work. A company that switches a type back on gets its old documents counted again.

## Out-of-scope changes (follow-up)

- `routes/web.php`: one new route, `admin.settings.document-types.company-toggle`.
- `app/Models/User.php`: PHPDoc `@return HasOne<Company, $this>` on `company()`.
- `app/Traits/CompanyFilterTrait.php`: PHPDoc generics (`@template TModel`) on `applyCompanyFilter()`. Both are type-only (no runtime change). They let Larastan see the real models; whole-app Larastan errors went from 301 to 226.

## Notes / observations (not fixed, outside the hotspot list)

- `GET /admin/settings/document-types/by-module` is registered after `GET /{id}`, so `show('by-module')` shadows it and it always returns 404. Nothing in `resources/`, `app/` or `routes/` calls it.
- Tenants who open a lookup URL directly (the sidebar hides these links via `@can('*.view')`) still see Add/Edit/Delete buttons on the five named-lookup pages, and clicking them returns 403. The Document Types page now hides them.
- With sharper types, Larastan flags `DriverComplianceDashboardController` reading `$driver->license_number`, `license_state` and `phone`, which are not attributes it knows on `Driver`. If those columns don't exist, the dashboard shows them as empty. This is pre-existing and was not investigated here.
- The guard uses `permission:` middleware, per convention. If a super-admin grants e.g. `fuel-types.create` to a custom role through the Roles UI, that role can edit global data. Use `role:super-admin` instead if that should never be possible.
- Larastan on the scope paths reports 1 pre-existing error that I did not touch: `DocumentType::$fillable` PHPDoc `array<int, string>` vs `list<string>` (`property.phpDocType`). The `method.notFound` for `maintenanceRecords()` is gone.

## Test run comparison

- Baseline `php artisan test`: 10 failed, 65 passed.
- After: 10 failed, 119 passed. The same 10 tests fail as in the baseline (`Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4, `ProfileTest` ×5), so there are no regressions.
- After the follow-up: 10 failed (the same 10), 142 passed. `tests/Feature/Modules/Lookups`: 77 passed.

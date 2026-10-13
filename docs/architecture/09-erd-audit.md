# 09 · ERD and foreign-key audit

This audit covers all 50 tables in `database/migrations` (commit `d1d4d8d`), the models in `app/Models`, and [../erd.md](../erd.md). Each finding gives:

- what is wrong,
- the evidence (**[V]** unless marked),
- the fix, which is reflected in [08-database-schema.md](08-database-schema.md) and [../erd-proposed.md](../erd-proposed.md).

Text columns that hold names are not wrong just because they hold names. Section 2 lists the ones that are legitimate snapshots and stay.

## 1. Findings

### Names, emails or copied values used where an id belongs

| ID | Finding | Evidence | Fix |
|---|---|---|---|
| F1 | `asset_groups.second_driver_name`, `_phone`, `_email` hold a co-driver as free text, with no FK. The search matches on the name (`AssetGroup.php:76`). | `…131750_create_asset_groups_table.php`; `AssetGroupController.php:162-164,187-189` | Replace with `co_driver_id` (cFK → `drivers`, RESTRICT) plus a CHECK that it differs from `driver_id`. Whether team driving is needed is still PENDING ([14](14-open-questions.md) §3). If it isn't, drop the columns without replacing them. |
| F2 | `asset_groups.primary_driver_name` is copied from `driver_id` when the group is saved (`AssetGroupController.php:184,354`). It goes stale when the driver is renamed. | Same files | Drop it and read the name through the `driver()` relation. Eager-load in the list. |
| F3 | `asset_groups.primary_driver_phone` and `primary_driver_email` are typed in by the user and can disagree with `drivers.main_phone` and `drivers.email`. | `AssetGroupController.php:160-161,185-186` | Drop them and use the driver's own contact details. |
| F4 | `companies.email` is globally unique and synced from the owner's login email (PO-22). It serves as both the company's contact and the login identity. Registration also rejects any email already used as a company email (`RegisteredUserController.php`, `unique:companies,email`). | companies migration | Remove the unique constraint. Add `billing_email`. Login identity lives only in `users.email`. **Decided D3b:** the PO-22 sync stops. |
| F5 | Once drivers can log in, `drivers.email` (HR contact) and `users.email` (login) both describe the same person. | New design | Keep both. They mean different things. The invitation copies the driver's email once and the two are never synced automatically (D3). |

### Missing or wrong constraints

| ID | Finding | Evidence | Fix |
|---|---|---|---|
| F6 | Some child rows can point at another company's parent. `service_logs`, `maintenance_schedules` and `asset_groups` hold `company_id` and parent ids with separate single-column FKs, so the database accepts mixed companies. Only the app prevents it (`vehicleRule()`, `Rule::exists(...)->where('company_id')`). | Migrations; `ServiceLogController.php:626-635`; `AssetGroupController.php:159-166` | Composite tenant FKs (cFK) on every tenant reference. Parents get `UNIQUE(company_id, id)`. |
| F7 | A vehicle or trailer can be in several active asset groups at once. `Vehicle::assetGroups()` and `Trailer::assetGroup()` are `hasOne`, so the extras are silently hidden. | `Vehicle.php:88-91`, `Trailer.php:68` | Generated `active_vehicle_id` / `active_trailer_id` columns, each with a unique index. Rename the relation to `activeAssetGroup()`. |
| F8 | Some uniqueness is enforced only in the app: `asset_groups (company_id, group_name)` (`AssetGroupController.php:381-383`), `document_types (module, name)`, and lookup `name` columns. | Migrations have none | Add database unique indexes. |
| F9 | Two `hasOne` relations have no unique constraint: `driver_documents.driver_id` (`Driver.php:100-102`) and `companies.user_id` (`User.php:54-57`). | Migrations | Make `driver_documents.driver_id` unique. Replace `companies.user_id` with `owner_user_id` and make `User::company` a `belongsTo` on `users.company_id`. |
| F12 | `policy_pdfs.company_id` is nullable **and** unique. MySQL allows any number of NULLs, so several "platform default" rows can exist. `PolicyPdf::resolveFor()` takes `first()`. | `…152037_create_policy_pdfs_table.php`; `PolicyPdf.php:33-34` | Make `company_id` NOT NULL. Move the platform defaults to `site_settings`. |
| F25 | `companies.slug` is nullable although it is unique and drives every public application URL. | companies migration | Make it NOT NULL. |

### Delete rules

| ID | Finding | Evidence | Fix |
|---|---|---|---|
| F10a | `drivers.user_id` is the **creator** and is `ON DELETE CASCADE`. Deleting the user who created drivers deletes those drivers' DQ files. | drivers migration | Rename it `created_by_user_id`, nullable, SET NULL. |
| F10b | `companies.user_id` is CASCADE. Deleting an owner deletes the whole tenant (PO-13 and PO-17 avoid this in code only). | companies migration | Use `owner_user_id` with RESTRICT. |
| F10c | `subscriptions.user_id` and `payments.user_id` are CASCADE, so financial history disappears with the user. | Billing migrations | Re-key both to `company_id` with RESTRICT. |
| F10d | `drivers.action_by` has no delete rule, so a user who approved or rejected a driver can never be hard-deleted. This is already listed in `erd.md`. | drivers migration | Rename it `decided_by_user_id`, SET NULL. |
| F10e | Every child of `companies` cascades: drivers, vehicles, trailers, logs, schedules, groups. | Migrations | RESTRICT, plus an explicit purge job after the retention period. |
| F10f | `asset_groups` cascades from driver, vehicle and trailer. `maintenance_schedules.vehicle_id` and `service_logs.vehicle_id` cascade too. Maintenance history disappears if a vehicle is hard-deleted. | Migrations | RESTRICT. Parents are soft-deleted anyway. |

### Code that disagrees with the schema

| ID | Finding | Evidence | Fix |
|---|---|---|---|
| F11 | `vehicle_documents`, `trailer_documents` and `document_types` have `deleted_at` columns, but their models lack `SoftDeletes`, so deletes are hard deletes. | Model and migration comparison | Add the trait. Soft deletes are kept for compliance history. |
| F23 | The code uses columns the schema doesn't have:<br>• `DriverComplianceDashboardController.php:58-61,141-144` reads `drivers.phone`, `license_number`, `license_state`.<br>• `MaintenanceSchedule::serviceLogs()` uses `maintenance_schedule_id`.<br>• Asset-group JS reads `license_plate` (`asset-group/index.blade.php:952,998`). | Files cited | Use `main_phone` and the current licence. Add `service_logs.maintenance_schedule_id`. Use the new `plate_number`. |

### Duplicated or conflicting data

| ID | Finding | Evidence | Fix |
|---|---|---|---|
| F13a | `licenses.repeat_license_number` is a form confirmation field that gets stored (`DriverCrudService.php:185,211`). | licenses migration | Drop the column. Keep validation with `same:license_number`. |
| F13b | The violation signature and date are stored on **every** `violations` row and again in `driver_documents`. | Both migrations | Keep only the `driver_documents` copy. |
| F13c | `drivers.rejection_date` (date) duplicates `rejected_at` (timestamp). | drivers migration | Drop `rejection_date`. |
| F13d | `service_documents` stores `file_name` and `file_path` (and the mime type and size) for each file. | service_documents migration | Move the file metadata to `stored_files`. |
| F14 | `maintenance_schedules.is_active` and `status` both describe the same state. `last_due_*` stores the last *completed* service. | Migration; `MaintenanceSchedule.php:149-198` | Drop `is_active`. Rename to `last_service_*` [R]. |
| F15 | `drivers.status` mixes two lifecycles. It covers the application (draft, pending, submitted, under_review, approved, rejected, withdrawn) and employment (active = hired, inactive). `DriverHireService.php:22,48,111` moves pending → active or rejected. | drivers migration | Split it into `application_status` and `employment_status`, and add `terminated_at`. Mapping in section 3. |
| F16 | Nothing marks a driver's current licence, so the drivers list loads every licence and sorts them in PHP. | `DriverController.php:35-37,82` | `licenses.is_current`, with a generated unique `current_driver_id`. |
| F17 | Medical-certificate data lives in three places: the file in `driver_documents.medical_card`, the expiry in `drivers.medical_certificate_expiration_date`, and nothing in the compliance engine. Licence expiry (`licenses.expires`) isn't tracked either. | `DriverComplianceService.php`; `DocumentTypeSeeder.php:17-29` | Add a system document type `medical_examiner_certificate` (`requires_expiry`). The wizard's medical-card upload creates that compliance document, and the drivers column is dropped. The compliance engine also checks the current licence's `expires`. [L] 49 CFR 391.41, 391.51. |
| F26 | Plans are duplicated per billing cycle ("Starter Monthly", "Starter Yearly"), and "Free Trial" is a plan. Entitlements would have to be copied onto every row. | `PlanSeeder.php:14-157` | Split into `plans` + `plan_prices`. The trial becomes a subscription state. |
| F27 | Billing is keyed to the user: `users.stripe_id`, `subscriptions.user_id`, `payments.user_id`. | Migrations; `CheckoutService.php` | Re-key to the company ([07](07-subscription-billing.md)). |
| F28 | `users.pm_type`, `pm_last_four` and `trial_ends_at` are left over from Cashier and unused. Cashier isn't installed. | users migration; `composer.json` | Drop them. |

### Typing, naming and indexes

| ID | Finding | Evidence | Fix |
|---|---|---|---|
| F19a | Numbers are stored as text: `accidents.number_of_injuries`, `number_of_fatalities`, `experiences.approx_miles`. | Migrations | Change to integer types. |
| F19b | Yes/no data mixes `enum('yes','no')` (hazmat, lcv_certificate, forfeitures, accidents, violations) with booleans (twic_card, passport). | Migrations | Use booleans throughout, since the schema is being rebuilt. |
| F19c | "No accident" and "no violation" are stored as rows (`accident = 'no'`). | accidents and violations migrations | Add `driver_documents.has_accidents` and `has_violations`. Store rows only for real events. |
| F20 | State and country are free text in six tables. `countries` and `states` are never referenced (already noted in `erd.md`). | Migrations | Use `state_code` and `country_code` char(2) columns, validated against the reference tables. |
| F21 | Naming is inconsistent: `trailers.equipment_types_id` (plural); `Vehicle::assetGroups()` is a `hasOne`; `Driver::driver_documents()` and `residence_addresses()` use snake case. | Migrations; models | Rename to `equipment_type_id`, `activeAssetGroup()`, and camelCase relation names. |
| F22 | Some indexes duplicate the FK's own index: `service_logs(vehicle_id)`, `states(country_id)`, `service_log_category(service_log_id)` (covered by the unique key's prefix). `vehicles` and `trailers` have single `unit_no` / `vin` indexes that tenant-scoped wildcard search never uses. | Migrations | Drop them. |

### Links with no foreign key

| ID | Finding | Evidence | Fix |
|---|---|---|---|
| F24 | Some links have no FK: Spatie `model_has_roles.model_id` (polymorphic), `sessions.user_id`, `notifications.notifiable_*`. | Framework design | Accepted. Role-pivot integrity is checked by a nightly query ([06](06-roles-permissions.md) §4.4). |

### `erd.md` itself

| ID | Finding | Evidence | Fix |
|---|---|---|---|
| F29 | `erd.md` is accurate against the migrations, but its "Known mismatches" names two relations wrongly. It says `Driver::driverDocument` and `Vehicle::assetGroup`; the code has `Driver::driver_documents()` and `Vehicle::assetGroups()`. Findings F7, F8, F11, F12, F13, F15, F17 and F23 are missing from it. | `erd.md` "Known mismatches"; models | `erd.md` is left untouched by request. This audit supersedes its mismatch list for the target design. |

### Earlier draft of this architecture

| ID | Finding | Evidence | Fix |
|---|---|---|---|
| F18 | The first draft forced a document to have exactly one owner, with `folder_id` as one of the possible owners. That ruled out a document that is about a trip event **and** filed in the trip folder. | Earlier plan | Ownership and filing are now separate. `documents` has at most one *subject*, plus an independent `folder_id`, with a consistency rule ([08](08-database-schema.md) §8). |

## 2. Legitimate snapshots to keep

These text values record what was true at a moment in time. They are not relationships and must not become live FKs.

| Column | Why it stays |
|---|---|
| `licenses.first_name`, `last_name` | The name printed on the licence, which can differ from the driver's current name |
| `employment_records.employer_*` | Previous employers are outside the system |
| `violations.vehicle_type`, `violation_location`, `offense` | The applicant's own declaration about past events |
| `driver_documents.*_signature`, `*_date_signed` | Legal attestations: what was signed, and when |
| `payments.amount`, `currency`, `raw` | Financial record from Stripe |
| `subscriptions.amount`, `currency` (plus the new `stripe_price_id`, `interval`) | The price actually charged |
| **New:** `trips.load_pay`, `vehicle_unit_no_snapshot`, `trailer_unit_no_snapshot` | Year-end reports must show the unit and pay at the time of the trip |
| **New:** `payroll_run_lines` rates and quantities | Pay history must not change when rates change |
| **New:** `fuel_purchases.price_per_gallon`, `total_amount` | Receipt values |
| **New:** `inspection_results.item_label` | The checklist wording the driver saw |

Everything else on trips, events and payroll refers to entities by id. Soft-deleted parents still resolve with `withTrashed()`.

## 3. Mapping for the `drivers.status` split (F15)

| Today `status` | `application_status` | `employment_status` |
|---|---|---|
| draft | draft | applicant |
| pending | pending | applicant |
| submitted | submitted | applicant |
| under_review | under_review | applicant |
| approved | approved | applicant |
| active (hired by `DriverHireService`) | approved (public applications) or null (created by staff) | active |
| inactive | unchanged | inactive |
| rejected | rejected | applicant |
| withdrawn | withdrawn | applicant |

[A] `submitted`, `under_review` and `approved` appear as labels (`DriverHireService.php:91-96`), but no transition was found that sets them. Confirm this before Phase 0 removes or keeps them.

## 4. Relationship cardinality: target

| Relationship | Cardinality | Enforced by |
|---|---|---|
| company → owner user | 1 : 0..1 | `owner_user_id` + cFK |
| company → users | 1 : N | `users.company_id` NOT NULL |
| user → driver (login) | 1 : 0..1 | `drivers.user_id` unique |
| driver → `driver_documents` | 1 : 0..1 | unique `driver_id` |
| driver → current licence | 1 : 0..1 | generated unique `current_driver_id` |
| vehicle → active asset group | 1 : 0..1 | generated unique `active_vehicle_id` |
| driver → in-progress trip | 1 : 0..1 | generated unique `trips.active_driver_id` |
| trip event → fuel purchase | 1 : 0..1 | unique `fuel_purchases.trip_event_id` |
| company → open subscription | 1 : 0..1 | generated unique `subscriptions.open_flag` |
| company + feature → open override | 1 : 0..1 | generated unique `open_flag` |
| plan + interval + currency → current price | 1 : 0..1 | generated unique `current_flag` |
| service log ↔ maintenance categories | N : M | `service_log_category`, unique pair [V] |
| conversation ↔ users | N : M | `conversation_participants` PK |

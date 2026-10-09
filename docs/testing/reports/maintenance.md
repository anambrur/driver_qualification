# Module report: `maintenance` (service logs & maintenance schedules)

Run date: 2026-10-10. Tests: `tests/Feature/Modules/Maintenance` (60 tests) and `tests/Unit/Modules/Maintenance` (15 tests). All 75 pass.

**Full suite:** 774 passed, 10 failed. The baseline before any change was 699 passed, 10 failed, and the same 10 tests fail in both runs: `Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4 and `ProfileTest` ×5. Nothing regressed.

**Larastan** on the scope files, the new factories and the new migration: 1 error, down from 35 before this run. That one is pre-existing and on a line I didn't touch: `ServiceLog::getFormattedTotalCostAttribute()` passes the `decimal:2` string to `number_format()`. The other 34 were `Relation '…' is not found` / `undefined property` errors. They are fixed by adding `BelongsTo`/`BelongsToMany`/`HasMany` return types to the relations of `ServiceLog`, `ServiceDocument` and `MaintenanceSchedule`.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| MNT-01 | P1 | FIXED | `MaintenanceTenancyTest.php::MNT-01: vehicle_id must belong to the tenant → it rejects a service log for another company's vehicle`; `…::it rejects moving a service log to another company's vehicle`; `…::it rejects a schedule for another company's vehicle`; `…::it rejects moving a schedule to another company's vehicle`; `…::it rejects a deleted vehicle of the tenant's own company` (log, schedule); regression guards `…::it still accepts the tenant's own vehicle`, `…::it still lets a tenant create a schedule for all vehicles`, `…::it still lets a super-admin use any company's vehicle` | `ServiceLogController.php`, `MaintenanceScheduleController.php` |
| MNT-02 | P1 | FIXED | `MaintenanceXssTest.php::MNT-02: the list escapes tenant-controlled data → it escapes vehicle fields and category names in the service log list`; `…::it escapes the title, category and vehicle fields in the schedule list` (title, category name); `…::it shows a super-admin another tenant's payload escaped too`; `…::it escapes the HTML helpers on the models`; `…::the modals build HTML from escaped values → it escapes category names, file names and vehicle options on the service log page`; `…::it builds the schedule dropdowns with text options`; regression guard `…::it escapes vehicle and category names in the server-rendered filters` (×2) | `ServiceLogController.php`, `MaintenanceScheduleController.php`, `app/Models/ServiceLog.php`, `app/Models/MaintenanceSchedule.php`, `admin/service-log/index.blade.php`, `admin/maintenance-schedule/index.blade.php` |
| MNT-03 | P2 | FIXED (`store()` had already been fixed by ACL-05; `update()` is fixed in this run) | `MaintenanceCompanyAttributionTest.php::MNT-03: records belong to their vehicle's company → it moves a service log to the new vehicle's company when a super-admin changes the vehicle`; `…::it moves a schedule to the new vehicle's company when a super-admin changes the vehicle`; `…::it ignores company_id and computed fields posted to a schedule update`; regression guards `…::it files a super-admin's new log and schedule under the vehicle's company`, `…::it keeps a schedule's company when a super-admin makes it apply to all vehicles`, `…::it keeps a tenant's records in the tenant's company on update` | `ServiceLogController.php`, `MaintenanceScheduleController.php` |
| MNT-04 | P2 | FIXED | Unit `MaintenanceScheduleNextDueTest.php` (15 tests: date schedules: never done, after last done (month end, leap day, year end, 365 days), no interval, due on the day; mileage: from odometer, from last service, no vehicle; engine hours: `it uses the engine hours from the vehicle's service logs`, `it is due once the logged engine hours reach the next due hours`, from zero, no vehicle; `changing the schedule type → it clears the next due values of the other types`); `MaintenanceScheduleCompletionTest.php::MNT-04: completing a schedule → it records the logged engine hours and schedules the next service from them`; `…::it reports the logged engine hours in the vehicle details`; `…::the details modal → it gets the fields it shows, including whether the schedule is due`; `…::editing a schedule → it keeps the due date when only the notes change`; `…::it switches to mileage and drops the old due date`; regression guards: mileage, date, another tenant (403), interval change | `app/Models/MaintenanceSchedule.php`, `app/Models/ServiceLog.php`, `MaintenanceScheduleController.php` |
| MNT-05 | P2 | FIXED (whether to keep `.doc`/`.docx` is DEFERRED, see item 1) | `ServiceDocumentFileTest.php::MNT-05: storage → it stores an uploaded document on the private disk under a random name`; `…::it stores documents added on edit on the private disk too`; `…::it stores PDF bytes uploaded as x.html under a .pdf name`; `…::it never links a document to /storage`; `…::it moves files already on the public disk to the private disk, and can move them back`; `…::download → it serves the file to the log's tenant`; `…::it returns 404, not a 500, for a document of a deleted log`; `…::delete → it deletes the file from the private disk with the document`; `…::it deletes the files removed on edit`; regression guards `…::it rejects files whose content is not an allowed type` (html, svg named .pdf, php named .pdf), `…::it still serves documents uploaded before the move, from the public disk`, `…::it lets a super-admin download any company's document`, `…::it refuses another tenant`, `…::it returns 404 when the file is missing from both disks`, `…::it deletes legacy files from the public disk too` | `ServiceLogController.php`, `app/Models/ServiceDocument.php`, migration `2026_10_10_000200_move_service_documents_to_private_disk.php` (new) |
| MNT-06 (new) | P1 | FIXED | `MaintenanceTenancyTest.php::MNT-06: the index filter lists only the tenant's vehicles → it does not show another company's vehicles` (logs, schedules); regression guard `…::it still shows a super-admin every company's vehicles` (×2) | `ServiceLogController.php`, `MaintenanceScheduleController.php` |
| MNT-07 (new) | P2 | FIXED | `MaintenanceListFiltersTest.php::MNT-07: the service log category filter → it lists only the logs in the chosen category`; regression guards `…::list scoping → it lists only the tenant's own logs and schedules` (×2), `…::it does not show another tenant's records by id`, `…::it requires the maintenance permissions` | `ServiceLogController.php` |
| MNT-08 (new) | P3 | FIXED | `MaintenanceListFiltersTest.php::it MNT-08: sends the log's categories to the edit form` | `ServiceLogController.php` |

**New factories:** `MaintenanceCategoryFactory`, `ServiceLogFactory` (`->forVehicle($vehicle)`), and `MaintenanceScheduleFactory` (`->forVehicle()`, `->mileage()`, `->engineHours()`). `MaintenanceCategory` has no `HasFactory` trait, and that model belongs to `lookups`, so tests call `MaintenanceCategoryFactory::new()` directly. Test fixtures are in `tests/Feature/Modules/Maintenance/Maintenance.php`.

### What changed

- **MNT-01: tenant check on `vehicle_id`.**
  - `store` and `update` in both controllers now validate `vehicle_id` with `Rule::exists('vehicles','id')->whereNull('deleted_at')`. For tenants the rule also adds `->where('company_id', <own company>)`.
  - Before, a tenant could file a log against another company's vehicle. `updateVehicleMetrics()` then raised that vehicle's odometer.
  - Soft-deleted vehicles are no longer accepted. The form dropdown never listed them anyway.
  - A super-admin may still pick any company's live vehicle.
- **MNT-02: stored XSS.**
  - **Server side:**
    - The service-log list's `vehicle_info` (unit no, year, make, model, VIN) and `service_info` (category names) now go through `e()`.
    - So do the schedule list's `schedule_info` (title, or the category name) and `vehicle_info`.
    - The unused model helpers `ServiceLog::categories_html`, `MaintenanceSchedule::vehicle_info` and `status_badge` are escaped as well.
  - **Client side:**
    - Both pages build the vehicle and category dropdowns with `new Option(...)`.
    - The service-log page runs category names (form checkboxes and view modal), uploaded and existing file names, and the status fallback through a new `escapeHtml()`.
    - Toast messages on both pages also go through `escapeHtml()`.
    - The remaining inline `onclick`s only pass numeric ids.
- **MNT-03: company on update.**
  - `store()` was already fixed by ACL-05 (`getOwningCompanyId`).
  - In `update()`, when a super-admin moves a log or schedule to another company's vehicle, the record now moves to that company.
  - A schedule switched to "all vehicles" keeps its company.
  - The schedule's `update($request->all())` is now `fill($validator->validated())`. Before, any posted `company_id`, `next_due_*` or `is_active` was saved. Tenants could already not move `company_id`.
- **MNT-04: engine hours and next-due calculation.**
  - **Engine hours.** Vehicles have no `engine_hours` column. The only engine hours the app records are the service logs' `current_engine_hours`, so the new `ServiceLog::currentEngineHoursFor($vehicleId)` returns the highest logged value. This works the same way as the odometer, which only goes up.
    - `calculateNextDue()`, `isDue()`, `markAsCompleted()` and `getVehicleDetails()` all use it, through the new `MaintenanceSchedule::currentEngineHours()`.
    - Before, engine-hour schedules were due "interval hours from 0" and were never due.
  - **Changing the schedule type.**
    - `calculateNextDue()` now clears the `next_due_*` values of the other types. A schedule switched from date to mileage used to keep showing (and, in `scopeDue`, matching on) its old date.
    - `update()` nulls the other types' intervals, the same way `store()` does.
  - **Editing a schedule.** `update()` used to recalculate the due date from today on every save, so changing only the notes moved the due date. It now recalculates only when the vehicle, type, interval or a `last_due_*` value changes.
  - **Details modal.**
    - `show()` never sent the fields the modal reads: `is_due`, `schedule_type_label`, `interval_text`, `next_due_text`, `status_badge` and `vehicle.full_name`.
    - Because of that, "Mark as completed" never appeared and those fields were blank. They are now appended to the response.
  - **Calendar math.** The month-end, leap-day and year-end cases were already correct, because the code adds days, not months. Those tests are kept as guards.
- **MNT-05: service documents.**
  - Uploads now go to the private `local` disk with `$file->store('service-documents/{log id}', 'local')`. That uses `hashName()`: a random name plus the extension guessed from the content.
    - Before, files went to the `public` disk with the **client's** extension, so PDF bytes named `x.html` were saved and served from `/storage` as HTML.
    - `original_name` is kept and used only as the download file name.
  - `downloadDocument` reads from the private disk. Files the migration hasn't moved yet are read from `public`.
    - It sends `X-Content-Type-Options: nosniff` and `Cache-Control: private, no-store`.
    - A missing file is a 404.
  - Download and delete go through `findAuthorizedDocument()`:
    - 404 when the log is soft-deleted. Before, that was a 500 from `authorizeCompanyAccess(null)`.
    - 403 for another company's document.
  - Deleting removes the file from both disks.
  - On edit, removed files are deleted only after the commit. Files stored by a failed save are removed.
  - `ServiceDocument::file_url` now points at the download route, never at `/storage`.
  - The new migration moves the existing files.
  - The `mimes` whitelist is unchanged: `pdf, jpg, jpeg, png, doc, docx`. It already rejects html, svg and php content. See deferred item 1.
- **MNT-06 (new): the filter dropdown leaked vehicles.** Both index pages built the vehicle filter with `Vehicle::orderBy('unit_no')->get()`, so every tenant saw every company's unit numbers, makes and models. It now goes through `applyCompanyFilter`.
- **MNT-07 (new): broken category filter.**
  - The service-log list filtered categories with `whereJsonContains('maintenance_categories', …)`, but categories are stored in a pivot table, not a JSON column.
  - Picking a category made the query fail. With `APP_DEBUG` on, the SQL error text went back to the browser in the DataTables `error` field.
  - It now uses the model's existing `withCategory()` scope.
- **MNT-08 (new): edit form categories.**
  - `edit()` didn't load `maintenanceCategories`, so the edit form opened with no category ticked.
  - Saving then failed with "Please select at least one maintenance category".

## Out-of-scope changes

- New migration `database/migrations/2026_10_10_000200_move_service_documents_to_private_disk.php` (MNT-05). It works the same way as the fleet-compliance one.
- New factories `database/factories/{MaintenanceCategory,ServiceLog,MaintenanceSchedule}Factory.php`. The module section asked for these.

No routes, shared traits, middleware, config or models outside the scope were changed. The `permission:maintenance.*` and `permission:scheduled.*` route middleware that the module section mentions as missing was already added by ACL-01 (access-control). A test guards it: `MaintenanceListFiltersTest::it requires the maintenance permissions`.

## Deferred items (questions for the product owner)

1. **MNT-05: should service documents still accept `.doc`/`.docx`?** They are now private, under random names, and only downloaded as attachments with `nosniff`, so they can't run in the browser. But Word files can carry macros, and the other upload modules only accept `pdf, jpg, jpeg, png`. Should we drop `doc,docx` (in the controller and in the page's JS check), or keep them because users attach Word invoices?
2. **"Mark as completed" sets the schedule's status to `completed`.** It also records the last service and calculates the next due date, which suggests the schedule should keep recurring. Should a recurring schedule stay `active` after it is marked done, so that `completed` only means "retired"? Right now a completed schedule shows as "Completed" with a future due date.
3. **Engine hours come from service logs.** The fix uses the highest `current_engine_hours` logged for the vehicle. Is that the intended source? The alternative is an `engine_hours` column on `vehicles`, updated like the odometer and editable on the vehicle form. That would be a new migration and a change to the `fleet-assets` module.
4. **Mileage and engine-hour schedules with "All vehicles".** `calculateNextDue()` needs a vehicle, so these schedules never get a due value and are never due. Should the form require a vehicle for those two types, or should a global schedule be tracked per vehicle?
5. **Carried over from access-control (ACL-05):** a super-admin's schedule with **no vehicle** still belongs to the super-admin's own company. Should a super-admin have to pick a company when creating one?

## Manual steps

1. **Back up `storage/app/public/service-documents/` and the database first.** Then run `php artisan migrate`, which runs `2026_10_10_000200_move_service_documents_to_private_disk`. It moves each file referenced by `service_documents.file_path` to `storage/app/private/service-documents/…`.
   - The paths in the database don't change.
   - It is idempotent, and `down()` moves the files back.
   - It was tested on SQLite with fake disks only. Run it on staging first.
   - If production runs on several app servers without shared storage, run it on the server that holds the uploads.
2. After the migration, `storage/app/public/service-documents/` should only contain files that no record references. Delete it once you have checked. Old `/storage/service-documents/...` links will return 404, which is intended. The app always linked to the download route anyway.
3. No config change is needed.

## Observations (not hotspots)

- **Deleting a log removes its files but keeps the rows.** `destroy()` soft-deletes the log and deletes its files from disk right away. The `service_documents` rows stay, so restoring a log (there's no UI for it) would give documents with no files. This is the existing behavior and wasn't changed.
- **The odometer never goes down.** `updateVehicleMetrics()` only raises the vehicle odometer. Correcting a log's odometer downward doesn't lower the vehicle's.
- **Changing an interval on a schedule that was never completed** recalculates the due date from today, because there is no recorded baseline. With a `last_due_*` value it counts from that.
- **`getDropdownData()` returns `companies`, which neither page uses.** For a super-admin that is every active company.
- **Last Larastan error:** `ServiceLog::getFormattedTotalCostAttribute()` calls `number_format()` on the `decimal:2` cast, which is a string.

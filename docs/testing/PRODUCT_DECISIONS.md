# Product Owner Decisions (runs 09–21)

Every question the module-fix runs `09-fleet-compliance` … `21-mail-delivery` left for the product owner is listed here in one place.
Sources: `docs/testing/runs/*.log` and the "Deferred items" / "Observations" sections of `docs/testing/reports/<slug>.md`.
Compiled 2026-10-11 from branch `auto/module-fixes`. Answers implemented 2026-10-11 (see each item's Agent notes).

There are two kinds of reader:

- **Product owner:** read the *Question*, pick an option, and fill in the `Decision:` block. Nothing else.
- **Coding agent:** implement **only** items whose `Status` is `ANSWERED`, following the *Implementation* steps for the chosen option and the rules in section 1.

---

## 0. How the owner answers

For each item, replace the decision block like this:

```text
Status:   ANSWERED            (was: OPEN)
Decision: B                   (option letter, or free text)
Notes:    keep for 90 days, then hard delete
Answered: 2026-10-12 by <name>
```

- If none of the options fit, write the rule you want in `Notes:` in plain words. The agent will follow the notes over the option text.
- `Status: WONTFIX` means "keep today's behaviour". The agent then only renames the test that records today's behaviour so it is a deliberate guard (see rule 1.4).
- Items in section 4 are confirmations (yes/no or a fact), not design choices.
- Several items are linked (for example PO-13 and PO-17 are both "delete cascades"). Answer linked items consistently; each item has a **Linked** line.

---

## 1. Rules for the coding agent (read before touching any item)

These rules apply to every item below. They exist because each decision lands in a different module and different files, and earlier runs went wrong when an agent guessed.

1. **One item per session.** Implement one `PO-xx` item, run its tests, update the docs (rule 1.7), and stop. Do not start the next item.
2. **Only `ANSWERED` items.** If the status is `OPEN`, or the decision is ambiguous, or the owner's notes contradict the option text in a way you can't resolve, do not implement it. Write the follow-up question under the item's `Agent notes:` and stop.
3. **Stay in the listed files.** Each item lists *Files* and *Tests*. Touching anything else is an out-of-scope change: keep it minimal and list it in the report (rule 1.7). Line numbers were correct on 2026-10-11; re-find the code by function name if they moved.
4. **Flip the pinning test first.** Most deferred items have a test whose name starts with `currently …` or ends with `(DEFERRED …)`. That test records today's behaviour. For the chosen option:
   - change its expectation to the new behaviour and rename it (drop `currently` / `DEFERRED`),
   - run it and see it **fail**,
   - then change the code until it passes.
   If the decision is `WONTFIX`, just rename it to a guard name, e.g. `it deletes the company … (decided PO-13, 2026-10-12)`.
5. **Project fix conventions still apply** (from `docs/testing/MODULE_FIX_PROMPT.md`): tenant scoping via `CompanyFilterTrait` / `company_id`; cross-tenant access → 404 (or 403 where the module already uses 403); escape output with `e()` / `@js`; uploads use a `mimes` whitelist + `hashName()` + private disk; never return exception text to the client.
6. **Schema and data:** new migrations only, never edit an existing one. Never delete or rewrite production data in a migration without the item saying so explicitly. Any migration must be safe to re-run and must have a `down()`.
7. **Finish every item with:**
   - `php artisan test` (full suite). Baseline is **1081 passed / 0 failed** after run 21. Any new failure is a regression: fix it or explain it.
   - `vendor/bin/phpstan analyse <touched files> --memory-limit=1G`: no new errors in touched lines.
   - Append a `## Decision follow-up: PO-xx` section to the module's `docs/testing/reports/<slug>.md` (what was decided, files changed, tests, manual steps).
   - In `docs/testing/MODULES.md`, change the hotspot's status from `DEFERRED` to `FIXED` (or `DISMISSED` for WONTFIX).
   - In this file, set the item's `Status:` to `DONE` and fill `Agent notes:` with the commit-ready summary.
8. **Never run** `migrate:fresh`, `db:seed` or `PermissionSeeder` against a non-test database, `composer update`, `git commit/push`, or anything that calls real Stripe, Vonage or SMTP.

A ready-to-paste prompt for this is at the end of the file (section 6).

---

## 2. Summary

| ID | Module (test dir) | Hotspot | Question in one line | Kind | Status |
|---|---|---|---|---|---|
| PO-01 | fleet-compliance (`FleetCompliance`) | FCMP-03 | Paginate the fleet compliance dashboard? Separate pages or tabs? | UX / perf | DONE |
| PO-02 | fleet-compliance (`FleetCompliance`) | FCMP-01 | Fleet "upload to all": which company for a super-admin? (Feature is broken in the UI today.) | Security / UX | DONE |
| PO-03 | maintenance (`Maintenance`) | MNT-05 | Keep accepting `.doc`/`.docx` for service documents? | Security | DONE |
| PO-04 | maintenance (`Maintenance`) | MNT-04 | "Mark as completed": keep a recurring schedule `active`? | Behaviour | DONE |
| PO-05 | maintenance + fleet-assets | MNT-04 | Engine hours: from service logs, or a column on vehicles? | Data model | DONE |
| PO-06 | maintenance (`Maintenance`) | MNT-04 | Mileage/engine-hour schedules on "All vehicles" are never due. Require a vehicle? | Behaviour | DONE |
| PO-07 | maintenance (`Maintenance`) | ACL-05 | Must a super-admin pick a company for a schedule with no vehicle? | Tenancy | DONE |
| PO-08 | fleet-assets (`FleetAssets`) | FLT-03 | Moving a vehicle/trailer to another company: move history, keep it, or forbid moves? | Data model | DONE |
| PO-09 | fleet-assets (`FleetAssets`) | FLT-04 | Should "All Status" include deleted assets? | UX | DONE |
| PO-10 | fleet-assets (`FleetAssets`) | FLT-02 | VIN unique per company (current) or system-wide? | Data model | DONE |
| PO-11 | otp-sms (`OtpSms`) | OTP-01 | Which countries may receive OTP SMS? Block Caribbean +1 codes? | Security / cost | DONE |
| PO-12 | otp-sms (`OtpSms`) | OTP-02 | Is 200 OTP sends/hour (all companies) the right global cap? | Cost | DONE |
| PO-13 | companies (`Companies`) | CMP-03 | Deleting a company hard-deletes everything + owner. Intended? Block deleting own company? | Data loss | DONE |
| PO-14 | companies (`Companies`) | CMP-05 | Policy PDFs: one platform-wide set, or per company? | Data model | DONE |
| PO-15 | billing (`Billing`) | BIL-03 | Free trial limited per account (today), per verified email, per company, or per card? | Revenue | DONE |
| PO-16 | subscription-lifecycle (`SubscriptionLifecycle`) | SUB-04 | Should `/dashboard` require an active subscription? | Onboarding | DONE |
| PO-17 | auth (`Auth`) | AUTH-04 | Owner deleting their profile wipes the whole company. Intended? Who sees "Delete account"? | Data loss | DONE |
| PO-18 | billing | (observation) | Is the Stripe account shared with other apps? | Confirmation | DONE |
| PO-19 | subscription-admin | SADM-03 | Admin grants that already became lifetime access: give them an end date again? | Data fix | DONE |
| PO-20 | several | (manual steps) | Production config confirmations (scheduler, cache, queue, servers) | Confirmation | ANSWERED |
| PO-21 | several | (manual steps) | Approve running the 4 new migrations (staging first) | Confirmation | ANSWERED |
| PO-22 | several | (observations) | Approve small follow-up fixes the runs noticed but left alone | Approval | DONE |

Suggested order to answer: the data-loss and security items first (PO-13, PO-17, PO-02, PO-11, PO-18, PO-21), then the rest.

---

## 3. Product decisions

### PO-01 · Paginate the fleet compliance dashboard? (FCMP-03)

- **Module / test dir:** `fleet-compliance` / `tests/Feature/Modules/FleetCompliance/`
- **Source:** `runs/09-fleet-compliance.log` Q1, `reports/fleet-compliance.md` Deferred 1
- **Today:** `/admin/compliance/fleet-compliance` (`ComplianceDashboardController::fleet()`, `app/Http/Controllers/ComplianceDashboardController.php:16`) loads **every** vehicle and trailer with their documents on one page. The query count is constant, but memory and render time grow with fleet size. The driver dashboard was already paginated on 2026-10-10 ("option B": statuses computed in SQL, 25 per page, summary cards counted over all pages).
- **Question:** Paginate the fleet page the same way? The page shows two lists (vehicles, trailers).

**Options**
- **A.** Paginate, 25 per page, each list with its own page parameter (`vehicles_page`, `trailers_page`), both on one page.
- **B.** Paginate, 25 per page, and split vehicles / trailers into two tabs (one list visible at a time).
- **C.** Leave unpaginated (fine while fleets stay small).

```text
Status:   DONE
Decision:A
Notes: need full system all list page in one pegination with server site pagination
Answered:
```

Agent notes (2026-10-11): Implemented as option A on the fleet compliance page: vehicles and trailers are paginated server-side, 25 per page, with `vehicles_page` / `trailers_page`, and both lists stay on one page. Summary cards are counted in SQL over every asset (`FleetComplianceService`). Follow-up question: the note says "full system all list page in one pagination with server side pagination". If that means every list page in the app should use server-side pagination, or vehicles and trailers should be merged into one list under a single paginator, please say so as a new item. Tests: `FleetComplianceDecisionsTest` PO-01.

**Implementation**
- Files: `app/Http/Controllers/ComplianceDashboardController.php` (`fleet()`), the fleet dashboard view under `resources/views/admin/compliance/`. Copy the pattern from `DriverComplianceDashboardController` (option B of 2026-10-10): compute status in SQL, paginate, count summary cards with separate aggregate queries over all rows.
- A: `->paginate(25, ['*'], 'vehicles_page')` and `'trailers_page'`; links keep the other list's page via `->appends(request()->except(...))`.
- B: as A plus tab UI; the active tab is a query param so pagination links keep it.
- Tests: extend `FleetComplianceDashboardTest.php` (FCMP-03 query-budget tests must stay green): 30 vehicles → page 1 shows 25, page 2 shows 5; summary cards still count all 30; tenant only sees own company.
- **Linked:** none.

---

### PO-02 · Fleet "upload to all": which assets, which company? (FCMP-01 follow-up)

- **Module / test dir:** `fleet-compliance` / `tests/Feature/Modules/FleetCompliance/`
- **Source:** `runs/09-fleet-compliance.log` Q2, `reports/fleet-compliance.md` Deferred 2
- **Today:**
  - (a) **Broken in the browser.** The checkbox in `resources/views/admin/compliance/partials/upload-document-modal.blade.php:66` has no `value`, so it posts `on`; the `boolean` rule in `DocumentUploadController::uploadDocument()` (`app/Http/Controllers/DocumentUploadController.php:160`) rejects it with 422. The API works with `upload_to_all=1`.
  - (b) If (a) is simply fixed, a **super-admin**'s "upload to all" attaches one file to **every company's** vehicles/trailers, because `applyCompanyFilter` returns everything for a super-admin (`DocumentUploadController.php:177`).
  - Drivers already have a rule (decided 2026-10-10): a super-admin must choose one company; tenants always use their own. See `DriverDocumentUploadController.php:115-134`.
- **Question:** Should fleet follow the driver rule? Vehicles/trailers have no status column, so "active only" can't apply. Should soft-deleted assets stay excluded (they are today)?

**Options**
- **A.** Same as drivers: super-admin must pick one company (`company_id` required when `upload_to_all`); tenants use their own company; soft-deleted assets excluded. *(The run's recommendation.)*
- **B.** Super-admin may upload to all companies at once (keep cross-company), tenants own company only.
- **C.** Remove "upload to all" for fleet entirely.

```text
Status:   DONE
Decision:A
Notes:    (also: keep soft-deleted assets excluded? yes/no) yes
Answered:
```

Agent notes (2026-10-11): Option A. The checkbox posts `1` (and `on` is accepted). A super-admin must send `company_id` (filled from the asset the modal was opened for, as for drivers); tenants use their own company. Soft-deleted assets are excluded. No assets returns 422 and stores no file. Tests: `FleetComplianceDecisionsTest` PO-02.

**Implementation**
- Files: `DocumentUploadController.php` (`uploadDocument()`), `upload-document-modal.blade.php` (checkbox gets `value="1"`; for A add a company select shown only to super-admins, copy from `driver-upload-document-modal.blade.php`).
- A: copy the validation from `DriverDocumentUploadController.php:115-121` (`Rule::requiredIf(...)`, `Rule::exists('companies','id')`), then scope the asset query to that company. Also return 422 when the selection has no assets (today it returns success with an empty message and leaves an orphan file — see PO-22).
- C: remove the checkbox and the `upload_to_all` branch; keep the FCMP-01 shared-file tests that still apply.
- Tests: `FleetComplianceSharedFileTest.php` + new: browser-style post `upload_to_all=on`/`1` → 200; super-admin without `company_id` → 422 (A); super-admin with company X → only X's assets get the document; tenant → only own assets.
- **Linked:** none, but must match the driver rule's behaviour if A.

---

### PO-03 · Service documents: keep `.doc` / `.docx`? (MNT-05)

- **Module / test dir:** `maintenance` / `tests/Feature/Modules/Maintenance/`
- **Source:** `runs/10-maintenance.log` Q1, `reports/maintenance.md` Deferred 1
- **Today:** service-log uploads accept `pdf, jpg, jpeg, png, doc, docx`. They are now private, random-named and downloaded as attachments with `nosniff`, so they can't run in the browser. But Word files can carry macros, and every other upload in the app accepts only `pdf, jpg, jpeg, png`.
- **Question:** Drop `doc,docx`, or keep them because users attach Word invoices?

**Options**
- **A.** Drop them (consistent with the rest of the app).
- **B.** Keep them.

```text
Status:   DONE
Decision:B
Notes:
Answered:
```

Agent notes (2026-10-11): Option B. `.doc/.docx` kept, no code change. MNT-05 note marked DISMISSED in MODULES.md.

**Implementation (A)**
- Files: `app/Http/Controllers/ServiceLogController.php:252` and `:347` (`mimes:` rules), `resources/views/admin/service-log/partials/form-modal.blade.php:229` (`accept=`), `resources/views/admin/service-log/index.blade.php:826` and `:870` (JS extension checks).
- Existing `.doc/.docx` files stay downloadable; don't delete them.
- Tests: `ServiceDocumentFileTest.php` — add "rejects a .docx upload on create and on edit"; existing allowed-type tests stay green.
- B: no code change; rename nothing, just mark DISMISSED.

---

### PO-04 · "Mark as completed" on a recurring schedule (MNT-04 follow-up)

- **Module / test dir:** `maintenance` / `tests/Feature/Modules/Maintenance/`
- **Source:** `runs/10-maintenance.log` Q2, `reports/maintenance.md` Deferred 2
- **Today:** `MaintenanceScheduleController::markAsCompleted()` (`app/Http/Controllers/MaintenanceScheduleController.php:395`) sets `status = 'completed'` **and** records the last service and calculates the next due date. A completed schedule shows "Completed" with a future due date.
- **Question:** Should a recurring schedule stay `active` after "Mark as completed", so `completed` only means "retired"?

**Options**
- **A.** Stay `active`; record last service, roll next due forward. Add a separate way to retire (status `completed`/`inactive` via edit).
- **B.** Keep today's behaviour (status becomes `completed`), but then stop calculating a next due date.
- **C.** Keep exactly as today.

```text
Status:   DONE
Decision:A
Notes:
Answered:
```

Agent notes (2026-10-11): Option A. `markAsCompleted()` keeps the schedule's status and rolls `last_due_*` / `next_due_*` forward. Retire a schedule by editing its status to `completed`. Button text unchanged (no note). Tests: `MaintenanceDecisionsTest` PO-04.

**Implementation**
- Files: `MaintenanceScheduleController.php` (`markAsCompleted()`), `app/Models/MaintenanceSchedule.php` (`calculateNextDue()` at `:149`, status badge map at `:130`), schedule list/detail views under `resources/views/admin/maintenance-schedule/` if the button label changes.
- A: remove `$schedule->status = 'completed'`; keep the `last_due_*` updates and `calculateNextDue()`. Rename the button/flash text to "Service recorded" if the owner agrees in Notes.
- B: keep status, skip the next-due calculation, clear `next_due_*`.
- Tests: `MaintenanceScheduleCompletionTest.php` — adjust the status expectation; add "the next due date moves forward and the schedule stays in the active filter" (A).
- **Linked:** PO-06 (same model, same calculation).

---

### PO-05 · Source of a vehicle's engine hours (MNT-04)

- **Module / test dir:** `maintenance` (+ `fleet-assets` if B)
- **Source:** `runs/10-maintenance.log` Q3, `reports/maintenance.md` Deferred 3
- **Today:** vehicles have no engine-hours column. The MNT-04 fix uses the **highest `current_engine_hours` on the vehicle's service logs** (`app/Models/MaintenanceSchedule.php` ~`:197`).
- **Question:** Is that the intended source?

**Options**
- **A.** Keep "highest logged value on service logs".
- **B.** Add `engine_hours` to `vehicles`, editable on the vehicle form, raised by service logs like the odometer.

```text
Status:   DONE
Decision:B
Notes:
Answered:
```

Agent notes (2026-10-11): Option B. New migration `2026_10_11_000100_add_engine_hours_to_vehicles` (backfilled from the highest logged hours, re-runnable). The vehicle form has an Engine Hours field (`nullable|integer|min:0`). Service logs raise it like the odometer, and schedules read it. Tests: `MaintenanceDecisionsTest` PO-05, `FleetAssetsDecisionsTest` PO-05.

**Implementation (B)**
- New migration: `vehicles.engine_hours` unsigned int nullable; backfill from `MAX(service_logs.current_engine_hours)` per vehicle (read-only source, safe to re-run).
- Files: `app/Models/Vehicle.php` (fillable/casts), `VehicleController.php` (validation `nullable|integer|min:0`), vehicle form view, `ServiceLog::updateVehicleMetrics()` in `app/Models/ServiceLog.php` (called from `ServiceLogController.php:299`; raise only, like odometer), `MaintenanceSchedule.php` (read `vehicle->engine_hours`).
- This crosses into `fleet-assets`: list it as out-of-scope in the maintenance report.
- Tests: Maintenance engine-hour tests + a FleetAssets test that the field saves and validates.
- **Linked:** PO-06, PO-22 (odometer never goes down — same function).

---

### PO-06 · Mileage / engine-hour schedules with "All vehicles" (MNT-04)

- **Module / test dir:** `maintenance` / `tests/Feature/Modules/Maintenance/`
- **Source:** `runs/10-maintenance.log` Q4, `reports/maintenance.md` Deferred 4
- **Today:** `calculateNextDue()` needs a vehicle. A mileage or engine-hour schedule with no vehicle never gets a due value and is **never due** — silently.
- **Question:** Require a vehicle for those two types, or track a global schedule per vehicle?

**Options**
- **A.** Require a vehicle when `schedule_type` is `mileage` or `engine_hours` (validation + form). Date schedules may still be "All vehicles".
- **B.** Treat "All vehicles" as one schedule per vehicle (bigger change: new child table or one row per vehicle on save).

```text
Status:   DONE
Decision:A
Notes:    (for A: what to do with existing vehicle-less mileage/engine-hour schedules — leave, or list them for manual fix?)
Answered:
```

Agent notes (2026-10-11): Option A. `vehicle_id` is required for mileage / engine-hour schedules on store and update, and the form shows a hint. Existing vehicle-less rows were left as they are (note not answered); the SQL to list them is in `reports/maintenance.md`. Tests: `MaintenanceDecisionsTest` PO-06.

**Implementation (A)**
- Files: `MaintenanceScheduleController.php` `store()` (`:148`) and `update()` (`:253`): `vehicle_id` → `Rule::requiredIf(in_array($request->schedule_type, ['mileage','engine_hours']))` plus the existing `vehicleRule()`; form JS shows "required" for those types.
- Existing rows: do not modify; put a read-only SQL in the report: `SELECT id, company_id, title FROM maintenance_schedules WHERE vehicle_id IS NULL AND schedule_type IN ('mileage','engine_hours') AND deleted_at IS NULL;`
- Tests: new validation tests in `Maintenance/`.
- B: write a design note in `Agent notes:` and stop; it needs a migration plan first.
- **Linked:** PO-04, PO-07.

---

### PO-07 · Super-admin schedules with no vehicle: which company? (ACL-05 carry-over)

- **Module / test dir:** `maintenance` / `tests/Feature/Modules/Maintenance/`
- **Source:** `runs/10-maintenance.log` Q5, `reports/maintenance.md` Deferred 5
- **Today:** a super-admin's schedule with no vehicle is saved under the super-admin's **own** company, so no tenant ever sees it.
- **Question:** Must a super-admin pick a company when creating a schedule with no vehicle?

**Options**
- **A.** Yes: super-admin must pick a company (select shown only to super-admins) when `vehicle_id` is empty; with a vehicle, the company comes from the vehicle.
- **B.** No: keep it under the super-admin's company.

```text
Status:   DONE
Decision:A
Notes:
Answered:
```

Agent notes (2026-10-11): Option A. A super-admin must pick a company for an all-vehicles schedule (company select shown to super-admins only, hidden when a vehicle is chosen). On update, the picked company is used, otherwise the schedule keeps its own. Tenants' `company_id` is ignored. Tests: `MaintenanceDecisionsTest` PO-07, `AccessControl/SuperAdminCompanyAttributionTest`.

**Implementation (A)**
- Files: `MaintenanceScheduleController.php` `store()`/`update()`; `getDropdownData()` (`:342`) already returns `companies` for super-admins (currently unused) — use it in the form.
- Validation: `company_id` → `Rule::requiredIf(isSuperAdmin && empty(vehicle_id))`, `Rule::exists('companies','id')`. Tenants: ignore any posted `company_id`.
- Tests: `MaintenanceCompanyAttributionTest.php`.
- **Linked:** PO-06 (if A there, this only matters for date schedules).

---

### PO-08 · Moving a vehicle/trailer to another company (FLT-03)

- **Module / test dir:** `fleet-assets` / `tests/Feature/Modules/FleetAssets/`
- **Source:** `runs/11-fleet-assets.log` Q1, `reports/fleet-assets.md` Deferred 1
- **Today:** a super-admin can change an asset's company in the edit form. The move is **refused with 422** while the asset has asset groups, service logs or maintenance schedules (check at `VehicleController.php:430`; same in `TrailerController.php`). Assets with no records move freely.
- **Question:** What should a move do?

**Options**
- **A. Move the history along:** set `company_id` of its service logs and schedules to the new company; close (end) its asset groups, since the group's driver belongs to the old company.
- **B. Keep history with the old company:** asset moves alone; the old company's records stay but stop showing the asset's current data.
- **C. Never allow moves:** remove the company picker from the super-admin edit form; refuse any `company_id` change.
- **D.** Keep today's rule (move only when no records).

```text
Status:   DONE
Decision:A
Notes:
Answered:
```

Agent notes (2026-10-11): Option A. A vehicle move takes its service logs and schedules (trashed too) to the new company. Its asset groups stay with the old company and are ended (inactive and soft-deleted, as AssetGroupController does), so the new company never sees the old driver. A trailer move removes the trailer from the old company's groups. Documents follow the asset. Known leftover: `AssetGroupController::restore()` can bring an ended group back; it should check the vehicle's company (new item if wanted). Tests: `FleetAssetsUpdateTest` FLT-03.

**Implementation**
- Files: `app/Http/Controllers/VehicleController.php` (`update()` `:240`, helper `:415-432`), `app/Http/Controllers/TrailerController.php` (`update()` `:200`), vehicle/trailer edit views.
- A: inside one DB transaction, update `service_logs`, `maintenance_schedules` (`company_id`), end active `asset_groups` rows (set their end/status the way `AssetGroupController` closes a group — check that controller, don't invent a new status). Uploaded documents: `vehicle_documents`/`trailer_documents` follow the asset.
- B: needs a decision on what "stop showing current data" means — write the follow-up in `Agent notes:` before coding.
- C: remove company field from the form for edit; validation ignores `company_id` on update; tests that moved empty assets must flip to "refused".
- Tests: `FleetAssetsUpdateTest.php` (`FLT-03: a super-admin moving an asset to another company → …`).
- **Linked:** asset-groups module (A touches groups — out of scope, list it).

---

### PO-09 · Should "All Status" include deleted assets? (FLT-04)

- **Module / test dir:** `fleet-assets` / `tests/Feature/Modules/FleetAssets/`
- **Source:** `runs/11-fleet-assets.log` Q2, `reports/fleet-assets.md` Deferred 2
- **Today:** status filter has "All Status", "Active", "Deleted". "All Status" shows live assets only (as before the run); deleted ones appear only under "Deleted", with a Restore button. The "Total: N" count excludes deleted.
- **Question:** Should "All Status" list both, with the red "Deleted" badge?

**Options**
- **A.** Yes, include deleted under "All Status" (Total counts both).
- **B.** No, keep as is. Optionally rename "All Status" → "All active" in Notes.

```text
Status:   DONE
Decision:A
Notes:
Answered:
```

Agent notes (2026-10-11): Option A. "All Status" lists live and deleted assets (deleted ones with their badge and Restore), "Active" only live, "Deleted" only deleted. The total counts both. Tests: `FleetAssetsListFiltersTest`.

**Implementation (A)**
- Files: `VehicleController.php` `index()` (`:35-37`) and the same block in `TrailerController.php`: when `status` is empty/`all` use `withTrashed()`, `active` → default, `deleted` → `onlyTrashed()`.
- Tests: `FleetAssetsListFiltersTest.php`; keep tenant scoping on `withTrashed()` queries.

---

### PO-10 · VIN unique per company or system-wide? (FLT-02)

- **Module / test dir:** `fleet-assets` / `tests/Feature/Modules/FleetAssets/`
- **Source:** `runs/11-fleet-assets.log` Q3, `reports/fleet-assets.md` Deferred 3
- **Today (after FLT-02):** unit no **and** VIN are unique **per company** (migration `database/migrations/2026_10_10_000300_scope_fleet_asset_unique_keys_to_company.php`). Reasons: a truck can be sold/leased between carriers and both keep it on record; a global check leaks another tenant's fleet.
- **Question:** Keep VIN per company, or must a VIN exist once in the whole system?

**Options**
- **A.** Per company (current).
- **B.** System-wide for VIN only (unit no stays per company). The error must **not** reveal which company has it.

```text
Status:   DONE
Decision:A
Notes:
Answered:
```

Agent notes (2026-10-11): Option A (as today). VIN stays unique per company. Guard test renamed `… (VIN per company, decided PO-10, 2026-10-11)`.

**Implementation (B)**
- New migration (don't edit `…000300…`): drop `['company_id','vin']` unique, add global `vin` unique, on both `vehicles` and `trailers`; pre-check for duplicates and abort listing them (same pattern as `2026_10_10_000400_make_company_slug_unique.php`).
- Files: `VehicleController.php` / `TrailerController.php` uniqueness rule (`:415` comment area) — generic message like "This VIN can't be used."
- Tests: `FleetAssetsUniquenessTest.php`.

---

### PO-11 · Which countries may receive OTP SMS? (OTP-01)

- **Module / test dir:** `otp-sms` / `tests/Feature/Modules/OtpSms/`
- **Source:** `runs/12-otp-sms.log` Q1, `reports/otp-sms.md` Deferred 1
- **Today:** `PhoneNumberService::isValid()` (`app/Services/PhoneNumberService.php:48`) accepts only **+1, +880 (Bangladesh), +91 (India)**, and refuses US premium codes 900 and 976 (`:107`). The public application form rejects every other country. +1 also covers Caribbean NANP countries (e.g. 876 Jamaica, 809 Dominican Republic), common toll-fraud targets.
- **Questions:** (1) US/Canada only? (2) Are +880 / +91 only for internal testing? (3) Block Caribbean +1 area codes?

**Options**
- **A.** US + Canada only; block non-US/CA NANP area codes; remove +880/+91 (or allow them only when `app()->environment('local','testing')` — say which in Notes).
- **B.** Keep +1, +880, +91; block Caribbean +1 codes.
- **C.** Keep as today.

```text
Status:   DONE
Decision:A
Notes:    (are +880/ test-only? yes)
Answered:
```

Agent notes (2026-10-11): Option A. Production accepts US and Canada only. Non-US/Canada NANP area codes (Caribbean, Bermuda) are blocked with 900/976 in `PhoneNumberService::BLOCKED_NANP_AREA_CODES`. +880/+91 validate only on `local` / `testing`. Tests: `OtpSmsPhoneValidationTest`; SubscriptionLifecycle green.

**Implementation**
- Files: `app/Services/PhoneNumberService.php` (`isValid()`, the `880`/`91` branches at `:31-38`, `:93-97`, the blocked-prefix check at `:107`). Put the blocked NANP area codes in one constant array with a comment naming the countries.
- Careful: `PhoneNumberService` is also used by `subscription-lifecycle` (expiry SMS, SUB-03). Run `tests/Feature/Modules/SubscriptionLifecycle` too.
- Tests: `OtpSmsPhoneValidationTest.php` (+880/+91 cases flip for A; add 876/809 rejected for A/B).
- **Linked:** PO-12.

---

### PO-12 · Global OTP send cap (OTP-02)

- **Module / test dir:** `otp-sms` / `tests/Feature/Modules/OtpSms/`
- **Source:** `runs/12-otp-sms.log` Q2, `reports/otp-sms.md` Deferred 2
- **Today:** max **200 OTP sends per hour across all companies**, read from config `services.vonage.otp_global_hourly_limit` (`app/Services/OTPService.php:334`), which isn't defined in `config/services.php`, so the code default `$defaultGlobalHourlyLimit = 200` (`:22`) applies. Per-IP route limits also exist.
- **Question:** Is 200/hour right?

**Options**
- **A.** Keep 200.
- **B.** Different number: ____ (write it in Notes).

```text
Status:   DONE
Decision:A
Notes:
Answered:
```

Agent notes (2026-10-11): Option A. 200/hour kept, no code change.

**Implementation (B)**
- Change `$defaultGlobalHourlyLimit` in `app/Services/OTPService.php:22`. There is no `otp_global_hourly_limit` key in `config/services.php` today, so the default is what production uses; optionally add the key backed by an env var (e.g. `VONAGE_OTP_GLOBAL_HOURLY_LIMIT`) and list it under manual steps.
- Tests: `OtpSmsRateLimitTest.php` reads the config, so it should pass unchanged; verify.

---

### PO-13 · Deleting a company (CMP-03)

- **Module / test dir:** `companies` / `tests/Feature/Modules/Companies/`
- **Source:** `runs/13-companies.log` Q1, `reports/companies.md` Deferred 1
- **Today:** `CompanyController::destroy()` (`app/Http/Controllers/CompanyController.php:339`) hard-deletes the company **and its owner user**. DB cascades then permanently delete all its drivers (licences, documents, employment records), vehicles, trailers, service logs, subscriptions and payments. Uploaded files stay on disk, orphaned. The delete button is also shown for the super-admin's **own** company, which would delete the super-admin account. Only super-admins can delete (guarded by tests).
- **Questions:** (1) Is the full cascade intended? (2) Block deleting your own company?

**Options (question 1)**
- **A.** Deactivate only (`status = inactive`), keep all data; owner can't log in.
- **B.** Soft-delete with a restore window of ___ days, then hard-delete by a scheduled job.
- **C.** Hard-delete as today, **plus** delete the stored files.
- **D.** Keep as today.

**Question 2:** block deleting own company? **yes / no**

```text
Status:   DONE
Decision:A (Q1 letter) + (Q2 yes)
Notes:
Answered:
```

Agent notes (2026-10-11): Option A + Q2 yes. "Delete" became "Deactivate": the company and owner are set inactive and all data is kept. The super-admin's own company is refused (422) and has no button. Tests: `CompanyDestroyTest`.

**Implementation**
- Files: `CompanyController.php` (`destroy()`), company list view (delete button), `app/Models/Company.php`.
- A: set `status = inactive` (+ owner `status` inactive, which the login already refuses — see AUTH-02); button label "Deactivate".
- B: needs a new migration adding `deleted_at` to `companies` + `SoftDeletes`; FK cascades won't fire on soft-delete. Add a pruning command only if Notes give the window. Every `Company::` query in the app must then exclude trashed — list the touched places.
- C: before deleting, collect file paths of all company documents (driver, vehicle, trailer, service documents, logo) on both `public` and `private` disks, delete rows in a transaction, delete files **after** commit.
- Q2 yes: refuse with 422 when `$company->id === auth()->user()->company_id`; hide the button for that row.
- Tests: `CompanyDestroyTest.php` (`::currently deletes the owner and cascades to the company drivers` is the pinning test).
- **Linked:** **PO-17** — the same cascade happens when an owner deletes their profile. Answer both the same way.

---

### PO-14 · Policy PDFs: platform-wide or per company? (CMP-05)

- **Module / test dir:** `companies` / `tests/Feature/Modules/Companies/`
- **Source:** `runs/13-companies.log` Q2, `reports/companies.md` Deferred 2
- **Today:** one global record (`PolicyPdf::first()`) holds the Alcohol & Drug Test policy and the General Work policy. Only super-admins edit it (`CompanyController::policyPDF()` / `policyPDFStore()` at `:376-381`); every company shares it.
- **Question:** Should each company upload its own?

**Options**
- **A.** Per company, with the platform set as fallback when a company hasn't uploaded one.
- **B.** Per company, no fallback.
- **C.** Keep one platform-wide set.

```text
Status:   DONE
Decision:A
Notes:
Answered:
```

Agent notes (2026-10-11): Option A. New migration `2026_10_11_000200_add_company_id_to_policy_pdfs` (null = platform set). `PolicyPdf::resolveFor()` falls back per policy to the platform file. Company owners manage their own set through the existing `companies.edit` permission (no seeder change); the super-admin manages the platform set. Files stay on the public disk because applicants open them on the public form. Tests: `PolicyPdfTest`, `CompanyDecisionsTest` PO-14.

**Implementation (A/B)**
- New migration: nullable `company_id` FK on `policy_pdfs` (null = platform default); the existing row stays as the default.
- Files: `CompanyController.php` policy actions (tenant `company` role gets write access to **its own** row — add a permission check that already exists in `PermissionSeeder`, don't invent one without saying so), `app/Models/PolicyPdf.php`, and every reader of `PolicyPdf::first()` (today: `ApplicationFormController.php`, `DriverController.php`, `CompanyController.php`) → resolve by company with fallback (A).
- Files are stored per company with `hashName()` on the private disk if they're tenant-specific.
- Tests: `PolicyPdfTest.php` (`::does not let a company owner upload global policy PDFs` must stay true for the **global** row).

---

### PO-15 · Who is a free trial limited to? (BIL-03)

- **Module / test dir:** `billing` / `tests/Feature/Modules/Billing/`
- **Source:** `runs/14-billing.log` decision, `reports/billing.md` Deferred 1
- **Today:** one free trial per **user account** (`TrialActivationService::activate()` → `$user->hasUsedTrial()`, `app/Services/Billing/TrialActivationService.php:31`). Anyone can register a new email and get another trial. Emails are **never verified** (`User` doesn't implement `MustVerifyEmail`), so adding `verified` to `/checkout/{plan}` would do nothing.
- **Question:** Limit trials how?

**Options**
- **A.** Per verified email → turn on email verification app-wide (auth-module change: `User implements MustVerifyEmail`, verification emails, `verified` middleware on checkout). Also fixes the Larastan error in `VerifyEmailController.php:22`.
- **B.** Per company, matched by ____ (name / phone / DOT / MC number — pick in Notes).
- **C.** Per payment card → collect a card for trials via Stripe Checkout `trial_period_days`, and refuse a trial when Stripe reports the card fingerprint was already used.
- **D.** Keep per account.

```text
Status:   DONE
Decision:A
Notes:
Answered:
```

Agent notes (2026-10-11): Option A. `User implements MustVerifyEmail`. Checkout and checkout-success need `verified`, so each verified email gets one trial. After verifying, the user returns to the checkout they started. Tests: `TrialAbuseTest`.

**Implementation**
- A: `app/Models/User.php` (`implements MustVerifyEmail`), `routes/web.php` (`verified` on checkout/trial routes), auth views. Check that registration → verify email → `/dashboard` still works (ties to PO-16). Large blast radius: run the full `Auth` and `Billing` dirs.
- B: in `TrialActivationService`, also refuse when any **other** company with the same normalized key has a trial row. Normalize (trim, lowercase; phone via `PhoneNumberService`).
- C: a Stripe flow change in `CheckoutService` + `WebhookProcessor`; use `Tests\Support\StripeFake`, never real Stripe. Write the design in `Agent notes:` first if the fingerprint check needs a new table.
- Tests: `TrialAbuseTest.php` (`::currently gives a brand-new account its own trial (per-user rule, see DEFERRED BIL-03)` is the pinning test).
- **Linked:** PO-16 (onboarding), PO-18 (Stripe).

---

### PO-16 · Should `/dashboard` require an active subscription? (SUB-04)

- **Module / test dir:** `subscription-lifecycle` / `tests/Feature/Modules/SubscriptionLifecycle/`
- **Source:** `runs/15-subscription-lifecycle.log`, `reports/subscription-lifecycle.md` Deferred
- **Today:** `/admin/dashboard` requires `Subscribed`; `/dashboard` (`routes/web.php:44-46`, same `DashboardController::index`) only needs `auth` + `verified`. Tests confirm it shows only the user's own company counts (no cross-tenant leak). Login, registration and email verification all redirect to `/dashboard`.
- **Question:** Should a new sign-up with no plan, or an expired account, still see `/dashboard` with its own counts, or be sent to pricing?

**Options**
- **A.** Keep open (they see their own counts; they hit the paywall on `/admin/*`).
- **B.** Gate it: add `Subscribed`; new and lapsed accounts land on `/pricing`.

```text
Status:   DONE
Decision:B
Notes:
Answered:
```

Agent notes (2026-10-11): Option B. `/dashboard` requires `Subscribed`. New and lapsed accounts go to pricing; super-admins pass. Tests: `DashboardGateTest`, `TrialAbuseTest` (register → verify → pricing → trial → dashboard).

**Implementation (B)**
- `routes/web.php:44`: `->middleware(['auth', 'verified', 'Subscribed'])`.
- Flip the first test in `DashboardGateTest.php` (`::currently lets an expired tenant open /dashboard (DEFERRED SUB-04)`) to expect a redirect to `pricing.plans`.
- Super-admins must not be redirected — check how `Subscribed` treats them and add a test.
- Run `Auth`, `Dashboard`, `Billing` test dirs: post-login/registration redirects must still end on a page the user can open.
- **Linked:** PO-15.

---

### PO-17 · Owner deleting their own profile wipes the company (AUTH-04)

- **Module / test dir:** `auth` / `tests/Feature/Modules/Auth/`
- **Source:** `runs/19-auth.log`, `reports/auth.md` Deferred
- **Today:** `ProfileController::destroy()` (`app/Http/Controllers/ProfileController.php:44`) deletes the user after a password re-entry. FK cascades (`companies.user_id`, `drivers.company_id`, `vehicles.company_id` …, all `ON DELETE CASCADE`) then permanently delete their company and every driver, vehicle, trailer, service log, maintenance schedule, asset group, subscription and payment.
- **Questions:** (1) Intended? (2) Should sub-users (`user` role) and super-admins see "Delete account" at all?

**Options (question 1)**
- **A.** Block self-deletion while the user owns a company (message: contact support / delete company first).
- **B.** Deactivate instead of delete (account inactive, data kept).
- **C.** Route deletion through support (hide the section; show a "contact support" note).
- **D.** Keep as today.

**Question 2:** who sees "Delete account": owners / sub-users / super-admins (list them).

```text
Status:   DONE
Decision:B (Q1 letter) + (Q2 roles) and owner
Notes:
Answered:
```

Agent notes (2026-10-11): Option B + owners only. Closing an account sets the owner and the company inactive and keeps all data (the same rule as PO-13). Only company owners see "Close Account"; others get 403. Tests: `AuthModuleTest` AUTH-04, `ProfileTest`.

**Implementation**
- Files: `ProfileController.php` (`destroy()`), `resources/views/profile/partials/delete-user-form.blade.php`, `resources/views/profile/edit.blade.php:24` (the `@include`), and the `/admin/profile` view if it includes the same partial.
- A: in `destroy()`, if the user owns a company (`Company::where('user_id', $user->id)->exists()`), return back with a `userDeletion` error and don't delete.
- B: set `status = inactive`, log out, keep the row (login already refuses inactive users — AUTH-02).
- Q2: wrap the `@include` in a role check and enforce the same check in `destroy()` (403), never UI-only.
- Tests: `AuthModuleTest.php` (`AUTH-04 profile deletion cascade → it currently deletes the company and its drivers …` is the pinning test).
- **Linked:** **PO-13** — answer consistently.

---

## 4. Confirmations and data decisions

### PO-18 · Is the Stripe account shared with other apps? (billing observation)

- **Source:** `runs/14-billing.log` "Worth checking", `reports/billing.md` Observations
- **Why it matters:** `CheckoutService` tags customers with `application`, `app_url`, `app_host` metadata "for shared Stripe accounts". If the account is shared, this app's webhook receives the other apps' events too. `WebhookProcessor` resolves the user only by `metadata.user_id` or customer id (`app/Services/Stripe/WebhookProcessor.php:56`) and never checks `metadata.app_host`. Another app's new subscription for its user #N would be credited to **this** app's user #N.
- **Question:** Shared, yes or no?

```text
Status:   DONE
Decision: (shared yes)
Notes:
Answered:
```

Agent notes (2026-10-11): Shared = yes. `WebhookProcessor` ignores (200, logged) billing events tagged with another `app_host`. Untagged events are processed as before (the default; tell us if they should be ignored instead). Tests: `WebhookSharedAccountTest`.

**Implementation (if shared = yes, or "not sure" — the guard is harmless when not shared)**
- In `WebhookProcessor::handle()` (`:35`), ignore (log at info, return 200) subscription/checkout/invoice events whose `metadata.app_host` is present and differs from this app's host. Decide with the owner what to do with events that have **no** `app_host` (older objects) — default: process them, as today.
- Tests: `Billing/WebhookIdempotencyTest.php`-style test with `StripeFake`: foreign `app_host` → no subscription created.

---

### PO-19 · Admin grants that already became lifetime access (SADM-03)

- **Source:** `runs/16-subscription-admin.log`, `reports/subscription-admin.md` Manual steps
- **Today:** the bug is fixed for the future, but grants that were already turned into unlimited access (by suspend + reactivate, or tenant cancel + resume) are still in the database. This read-only query finds them:

```sql
SELECT id, user_id FROM subscriptions
WHERE stripe_subscription_id IS NULL AND billing_cycle <> 'trial'
  AND stripe_status = 'active' AND cancel_at_period_end = 0 AND ends_at IS NULL;
```

- **Question:** After running the query in production, what should happen to the rows found?

**Options**
- **A.** Leave them (treat as intentional free accounts).
- **B.** Give each an end date of ____ (e.g. original grant length from `created_at`, or a fixed date) — the owner or an admin does it from the subscription-admin page, row by row.
- **C.** Write a one-off migration/command to set the end date (needs the rule in Notes).

```text
Status:   DONE
Decision:B
Notes:    (how many rows did the query return?) but at this moument this system is development face so there is no any real account
Answered:
```

Agent notes (2026-10-11): Option B, nothing to fix while there are no real accounts. No code. Re-run the query before go-live and fix any rows from the subscription-admin page.

- Agent: only C is code work. Make it an artisan command with `--dry-run` as default, not a migration.

---

### PO-20 · Production configuration confirmations

Answer each line `yes` / `no` / `will do`. No code changes unless noted.

```text
Status:   ANSWERED
[ not sure may be yes] Laravel scheduler (cron `schedule:run` every minute) runs in production.
    Needed for: OTP record pruning (OTP-04), subscription expiry reminders (SUB-01/02), compliance digest.
[ database ] CACHE_STORE is `database` or `redis` (not `file`/`array`) in production.
    Needed for: OTP wrong-code counter (OTP-03), global OTP cap (OTP-02), reminder throttle (REM-02), daily digest dedupe.
[ yes ] A queue worker runs in production (`queue:work`), and someone watches the error log
    for "Queued mail delivery failed" entries (MAIL-02).
[ no ] Does the scheduler run on MORE THAN ONE server?  yes / no
    If yes, the agent adds `->onOneServer()` to the compliance digest schedule (routes/console.php).
[ ] Will the app ever run on Octane / Swoole / RoadRunner?  yes / no
    If yes, `CompanyFilterTrait` caching the company per controller instance becomes a cross-tenant bug
    (see reports/fleet-assets.md Observations) and must be fixed first.
Answered:
```

Agent notes (2026-10-11): No code needed. Scheduler: "not sure, maybe yes". Please confirm before go-live, because OTP pruning, expiry reminders, the compliance digest and the new `subscriptions:close-expired-trials` (daily 10:00) depend on it. Cache = database: fine. Queue worker: yes. One scheduler server: no `onOneServer()` added. Octane: still unanswered. Please answer before ever enabling Octane/Swoole/RoadRunner.

---

### PO-21 · Approve running the new migrations (staging first)

These were only tested on SQLite with fake disks. Each needs a backup first.

| Migration | From | Before running | Effect |
|---|---|---|---|
| `2026_10_10_000100_move_fleet_compliance_files_to_private_disk` | FCMP-02 | Back up `storage/app/public/documents/{vehicles,trailers}` + DB | Moves fleet docs to private disk; old `/storage/...` links → 404 (intended). Re-runnable, reversible. |
| `2026_10_10_000200_move_service_documents_to_private_disk` | MNT-05 | Back up `storage/app/public/service-documents/` + DB | Same for service documents. |
| `2026_10_10_000300_scope_fleet_asset_unique_keys_to_company` | FLT-02 | Back up DB; run on **MySQL** staging | Unit no / VIN unique per company. (Affected by PO-10.) |
| `2026_10_10_000400_make_company_slug_unique` | CMP-04 | Run the duplicate-slug SQL in `reports/companies.md` | Aborts and lists duplicates if any; rename them, re-run. |

```text
Status:   ANSWERED
[ yes ] Run on staging — result:
[ ] Run on production — date:
Answered:
```

Agent notes (2026-10-11): Approved for staging, but the agent can't run it. Manual step: back up, then run `php artisan migrate` on staging and fill in the result. This run adds two migrations to the list: `2026_10_11_000100_add_engine_hours_to_vehicles` (PO-05) and `2026_10_11_000200_add_company_id_to_policy_pdfs` (PO-14). Both are additive, re-runnable and have a `down()`.

---

### PO-22 · Approve small follow-up fixes (observations the runs left alone)

Tick the ones you want fixed. Each is small and has no product ambiguity; the agent treats each ticked line as its own item.

```text
Status:   DONE
fleet-compliance
[ yes ] Restrict document_type_id to fleet types (`->where('module', $assetType)`), so a driver document type can't be attached to a vehicle.
[ yes] "Warning" card counts Critical too, and the "N Vehicles Need Attention" badge includes trailers — show separate, correct counts.
[ yes] `days_until_expiry` can render as "10.0 days" — cast to int.
[ yes] "Upload to all" with no assets returns success and leaves an orphan file — return 422 and delete the file (do with PO-02).
maintenance
[ yes] Deleting a service log deletes its files but keeps the `service_documents` rows — delete the rows too (or keep files until hard delete).
[ yes] Odometer never goes down when a log's odometer is corrected — allow lowering it when the corrected log was the highest one.
[ yes] Remove `companies` from `getDropdownData()` if PO-07 is not A.
fleet-assets
[ yes] `getVehicleDetails()` returns `license_plate`, which doesn't exist — remove it.
[ yes] VIN rule is only `size:17` — also reject I, O, Q.
[yes ] Deleting a vehicle leaves its asset group active — end the group (asset-groups module).
companies
[ yes] "Remove current logo" checkbox is ignored, and replacing a logo leaves the old file — honour it and delete old files.
[ yes] Owner changing login email on Profile doesn't update `companies.email` — keep them in sync (or decide which is the contact email).
billing
[ yes] Expired free trials stay `stripe_status = 'trialing'` forever — scheduled job to mark them `canceled` (access is already correct).
models (Larastan)
[ yes] Add relation return types to `Driver`, `Vehicle`, `Trailer`, `VehicleDocument`, `TrailerDocument`, `Subscription` (clears most remaining phpstan errors in 6 modules).
Answered:
```

Agent notes (2026-10-11): All ticked lines done. Fleet compliance: fleet types only, separate Warning/Critical counts, whole-day expiry, 422 with no orphan file. Maintenance: log delete removes document rows; a corrected highest log lowers the odometer / engine hours; the `companies` line is not applicable (PO-07 = A). Fleet assets: `license_plate` removed, VIN rejects I/O/Q, deleting a vehicle ends its group. Companies: remove/replace logo deletes the old file, Profile email syncs `companies.email`. Billing: `subscriptions:close-expired-trials`, daily. Models: relation return types on Driver, Vehicle, Trailer, VehicleDocument, TrailerDocument, Subscription; phpstan is clean on those six files.

---

## 5. Not in this file

Earlier runs (01–08) also left questions in `docs/testing/MODULE_FIX_PROMPT.md` → "Decisions the agent will defer to you": LKP-01 (lookups global vs per-tenant), APP-07 (driver email uniqueness), ACL-02 (dependency upgrades), ACL-06 (production `.env` / `.htaccess`). They are out of scope here (runs 09–21 only). Runs 17 (driver-hiring), 18 (compliance-reminders), 20 (dashboard) and 21 (mail-delivery) had no deferred questions; their manual steps are folded into PO-20.

---

## 6. Prompt for the coding agent

Paste below into a new session and change the `ITEM:` line.

```text
ITEM: PO-13

You are implementing ONE product-owner decision in a Laravel app (DriverFilesHub).

1. Read docs/testing/PRODUCT_DECISIONS.md, sections 1 (rules) and the section for ITEM.
2. If the item's Status is not ANSWERED, or the decision is unclear, write your question
   under "Agent notes:" for that item and STOP. Do not guess.
3. Read docs/testing/MODULE_FIX_PROMPT.md (fix conventions, hard rules) and the item's
   module section in docs/testing/MODULES.md (scope files, routes).
4. Baseline: run `php artisan test` and save the failing list (expected: none).
5. Follow the item's Implementation steps for the chosen option:
   - flip/rename the pinning test first and see it fail,
   - make the smallest change that makes it pass,
   - add the extra tests the item lists.
6. Finish as in rule 1.7: full test suite, phpstan on touched files, report follow-up
   section, MODULES.md status, and Status: DONE + Agent notes in PRODUCT_DECISIONS.md.
7. Stop. Do not start another item. Do not commit.
```

# Module report: `fleet-compliance` (vehicle/trailer compliance dashboard & documents)

Run date: 2026-10-10. Tests: `tests/Feature/Modules/FleetCompliance` (75 tests, all passing). There is no unit test directory for this module.
Full suite: 699 passed, 10 failed. The baseline before any change was 623 passed, 11 failed. All 10 failures are in the baseline: `Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4 and `ProfileTest` ×5. The 11th baseline failure, `AssetGroups\AssetGroupListFiltersTest › it applies the quick search within the tenant's own…`, passes now; this module does not touch it, so it looks flaky. Nothing regressed.
Larastan on the scope files and the new migration: 21 errors, down from 33. All 21 are the existing `Relation '…' is not found` / `undefined property` kind, because the `Vehicle`, `Trailer`, `VehicleDocument` and `TrailerDocument` relations have no return types. The new errors this run introduced in `DocumentUploadController` were fixed: `has_document`, the `applyCompanyFilter` union type, and the dynamic relation access.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| FCMP-01 | P1 | FIXED | `FleetComplianceSharedFileTest.php::FCMP-01 → it stays for the other assets when one asset's document is replaced` (vehicle, trailer); `…::it stays for the other assets when one asset's document is deleted` (vehicle, trailer); `…::it is deleted once the last document using it is replaced` (vehicle, trailer); `…::it replaces every asset's shared file when "upload to all" runs again, and deletes the old one`; `…::it still deletes the file of a document nobody else uses` (vehicle, trailer) | `DocumentUploadController.php` |
| FCMP-02 | P1 | FIXED | `FleetComplianceFileTest.php::FCMP-02 → it stores an uploaded document on the private disk`; `…::it serves the file to the asset's tenant through the view and download routes`; `…::it lets a super-admin open any company's document`; `…::it refuses another tenant` (view, download × vehicle, trailer); `…::it refuses guests and users without fleet permissions`; `…::it still serves documents uploaded before the move, from the public disk`; `…::it deletes a legacy file from the public disk when the document is replaced`; `…::it returns 404 when the file is missing from both disks`; `…::it links the dashboard preview to the authorized view route, never to /storage`; `…::it moves files already on the public disk to the private disk, and can move them back`; `…::it stores PDF bytes uploaded as x.html under a random .pdf name`; `…::it gives two uploads of the same client name different stored names`; regression guard `…::it rejects files whose content is not jpg, png or pdf` (html, svg named .pdf, php named .pdf) | `DocumentUploadController.php`, `admin/compliance/fleet.blade.php`, migration `2026_10_10_000100_move_fleet_compliance_files_to_private_disk.php` (new) |
| FCMP-03 | P2 | FIXED (the N+1); pagination DEFERRED | `FleetComplianceDashboardTest.php::FCMP-03: query budget → it lists the upload modal's assets in a constant number of queries` (vehicles, trailers); regression guards `…::it still flags which assets already have the document` (vehicles, trailers), `…::it loads the dashboard in a constant number of queries` | `DocumentUploadController.php` |
| FCMP-04 | P2 | FIXED (asset type); the `warning` half is DISMISSED | `FleetComplianceDashboardTest.php::FCMP-04: compliance status → it returns 404 for an asset type other than vehicle or trailer` (view, download); regression guards `…::it counts an asset whose only problem is an expiring document as Warning`, `…::it is danger when a document is missing or expired, even if another one is only expiring` (missing, expired, expires today), `…::it is compliant at 100% when every document is valid`, `…::it returns warning from the details endpoint too` (each for vehicle and trailer) | `DocumentUploadController.php` |
| FCMP-05 | P3 | FIXED | `FleetComplianceErrorsTest.php::FCMP-05 → it logs a failed asset list and returns a generic message` (vehicles, trailers); `…::it logs a failed upload, returns a generic message and removes the stored file`; `…::it returns 404 for an unknown document on delete`; `…::it returns 404 for an unknown asset on upload, and stores nothing`; `…::it returns 403 and keeps the document when another tenant deletes it`; `…::it returns 403 and stores nothing when uploading for another tenant's asset`; regression guard `…::it still uploads to "all" only within the tenant` | `DocumentUploadController.php` |
| FCMP-06 (new) | P1 | FIXED | `FleetComplianceXssTest.php::FCMP-06 → it escapes every tenant-controlled field in the details modal`; `…::it does not put the document type name in an inline onclick`; `…::it builds the upload dropdown with text options, not innerHTML`; regression guard `…::it escapes the unit number and driver name in the server-rendered list` (vehicle, trailer) | `admin/compliance/fleet.blade.php`, `admin/compliance/partials/upload-document-modal.blade.php` |
| FCMP-07 (new) | P1 | FIXED | `FleetComplianceErrorsTest.php::FCMP-07 → it are not served or deleted for another tenant` (vehicle, trailer); regression guard `…::it are not served to their own tenant either, like the rest of a deleted asset` | `DocumentUploadController.php` |

New factories: none. `Vehicle`, `Trailer` and `DocumentType` (`->module('vehicle'|'trailer')`) already exist. Test fixtures are in `tests/Feature/Modules/FleetCompliance/FleetCompliance.php`.

### What changed

The fixes follow the ones made in `driver-compliance` (DCMP-01/02/04/06/07/08), so both upload controllers now work the same way.

- **FCMP-02: private storage, random names.**
  - Uploads go to the private `local` disk (`storage/app/private/documents/{vehicles,trailers}/…`) with `store()`. That uses `hashName()`: a random name plus the extension guessed from the content. Before, the file was `time()_<client name>` on the `public` disk.
  - PDF bytes uploaded as `x.html` are now saved as `<random>.pdf`. The `mimes:jpg,jpeg,png,pdf` whitelist was already correct.
  - `viewDocument()` and `downloadDocument()` stream from the private disk. Files the migration hasn't moved yet are read from the `public` disk instead. Both responses send `X-Content-Type-Options: nosniff` and `Cache-Control: private, no-store`.
  - The details modal's image preview used `/storage/${doc.file_path}`. It now uses `admin.compliance.documents.view`.
  - The new migration moves every file referenced by `vehicle_documents.file_path` or `trailer_documents.file_path` from `public` to `local`. Paths stay the same. Missing files are skipped, re-running it is safe, and `down()` moves the files back.
- **FCMP-01: a file shared by "upload to all".** "Upload to all" still stores one file for every asset.
  - A file is now deleted only when no document of that asset type references it any more (`deleteFileIfUnused()`, which checks both disks).
  - Replaced files are deleted after the transaction commits. Before, they were deleted inside the transaction, so a failed upload rolled the rows back to a file that no longer existed.
- **FCMP-03: N+1 in the asset lists.** `getVehiclesList` and `getTrailersList` ran one `exists` query per asset. They now use `withExists('documents as has_document')`, which is one query. The response is unchanged.
  - The dashboard itself (`fleet()`) already runs a constant number of queries, because it eager-loads everything. It still loads every asset unpaginated: see Deferred item 1.
- **FCMP-04: compliance math.**
  - The controller does **not** have the DCMP-03 bug: missing or expired gives `danger`, only expiring gives `warning`, all valid gives `compliant`. The tests are kept as a regression guard.
  - The view and download routes treated any `{assetType}` other than `vehicle` as a trailer, so `/documents/5/anything/view` served trailer document 5. Anything other than `vehicle` or `trailer` is now a 404. Delete already validated `in:vehicle,trailer`.
- **FCMP-05: exception text.**
  - The list, upload and delete endpoints now log failures with `Log::error`. They return `Failed to load vehicles.`, `Failed to load trailers.`, `Failed to upload document.` or `Failed to delete document.`
  - The asset and document lookups and the tenant checks moved out of the `try`. Before, the catch-all turned them into a 500 with the exception text ("No query results for model …", "You do not have permission …"). Now an unknown record is 404 and another tenant's is 403, the same as the view and download routes.
  - A failed upload removes the file it stored.
- **FCMP-06 (new): stored XSS in the dashboard modals.**
  - The details modal built its HTML with template literals and `innerHTML`. That covered make, model, year, VIN, the document type name and description, and the missing/expired list.
  - The upload dropdown did the same with the unit number.
  - All of these are entered by tenant users, and a super-admin's dashboard shows every tenant's. They now go through `escapeHtml()`. The dropdown is built with `new Option(...)`.
  - The image preview button was `onclick="previewImage('<url>', '<type name>')"`. It now uses `data-action="preview-document"`, `data-url` and `data-title`, with one delegated listener.
  - The server-rendered list was already escaped (Blade `{{ }}`). A test guards it.
- **FCMP-07 (new): a deleted asset's documents had no tenant check.**
  - `viewDocument`, `downloadDocument` and `deleteDocument` ran `authorizeCompanyAccess` only `if ($document->vehicle)` (or `->trailer`). Vehicles and trailers are soft-deleted, so the parent loads as `null` and the check was skipped.
  - Any user with `fleets.edit` in any company could delete a deleted asset's documents by id. Any user with `fleets.dashboard` could read them as long as the files were on the public disk.
  - All three now go through `findAuthorizedDocument()`: 404 when the asset type is unknown or the document or its asset is gone, 403 for another company.

## Out-of-scope changes

- `resources/views/admin/compliance/partials/upload-document-modal.blade.php`: the fleet upload modal, included only by `admin/compliance/fleet.blade.php`. It is not `fleet*` by name. One change: the asset dropdown is built with `new Option(...)` instead of `innerHTML` (FCMP-06). The same partial-level fix was made for drivers in DCMP-08.
- New migration `database/migrations/2026_10_10_000100_move_fleet_compliance_files_to_private_disk.php` (FCMP-02).

No routes, shared traits, middleware, config or models were changed.

## Deferred items (questions for the product owner)

1. **FCMP-03: should the fleet dashboard be paginated?** It loads every vehicle and trailer with their documents. The query count is constant, but memory and render time grow with the fleet. The driver dashboard was paginated on 2026-10-10 with option B: statuses computed in SQL, 25 per page, summary cards counted over all pages. The fleet page shows two lists (vehicles and trailers) on one page. Should we do the same here? If so, should each list get its own page parameter, or should they become tabs?
2. **Fleet "upload to all": which assets, and for a super-admin, which company?**
   - (a) In the browser the feature fails today. The modal's checkbox has no `value`, so it posts `on`, and the `boolean` rule rejects it with 422 "The upload to all field must be true or false." (The API works with `upload_to_all=1`.)
   - (b) If it is fixed, a **super-admin**'s "upload to all" would attach one file to **every company's** vehicles or trailers. `applyCompanyFilter` returns everything for a super-admin.
   - For drivers it was decided on 2026-10-10: a super-admin must choose one company, and tenants always use their own company.
   - Should fleet follow the same rule? Vehicles and trailers have no status column, so "active only" cannot apply. Should soft-deleted assets stay excluded, as they are now?
   - Until this is answered, the checkbox is left as it is, so the cross-company upload is not reachable from the UI.

## Manual steps

1. **Back up `storage/app/public/documents/vehicles/`, `storage/app/public/documents/trailers/` and the database first.** Then run `php artisan migrate`, which runs `2026_10_10_000100_move_fleet_compliance_files_to_private_disk`. It moves each referenced file to `storage/app/private/documents/{vehicles,trailers}/`. It is idempotent, and `down()` moves the files back.

   It was tested on SQLite with fake disks only. **It has not been run on MySQL or on real storage**, so run it on staging first. If production runs on several app servers without shared storage, run it on the server that holds the uploads.
2. After the migration, the public `documents/vehicles` and `documents/trailers` folders should only contain files that no record references, such as old replaced files. Delete them once you have checked. Old `/storage/documents/{vehicles,trailers}/...` links (bookmarks, emails) will return 404. That is intended.
3. No config change is needed. The private `local` disk already exists.

## Observations (not hotspots)

- **Any document type id is accepted.** `document_type_id` uses `exists:document_types,id`, so a driver document type can be attached to a vehicle. It would never show on the dashboard. Adding `->where('module', $assetType)` would close this.
- **The "Warning" card counts Critical assets too.** `$totalWarning` counts `warning` and `danger` together, and the "N Vehicles Need Attention" badge uses that same number, which includes trailers.
- **`days_until_expiry` can be a float.** Carbon 3's `diffInDays()` returns a float. Between two midnights it is a whole number, but the type is `float`, so the modal may show "Expires in 10.0 days" depending on JSON encoding.
- **`upload_to_all` with no assets returns success with an empty message.** It also leaves the stored file behind, which nothing references. The driver controller returns 422 in this case.
- **`VehicleDocument`/`TrailerDocument` don't use `SoftDeletes`.** Their tables have a `deleted_at` column (from the create migrations), but deletes are hard. This is the existing behavior and wasn't changed.
- **Relations have no return types.** `Vehicle`, `Trailer`, `VehicleDocument` and `TrailerDocument` relations have no `BelongsTo`/`HasMany` return types. That causes all the remaining Larastan errors here.

## Decision follow-up: PO-01

Decided 2026-10-11: option A. Vehicles and trailers are paginated server-side on one page, 25 per page, each with its own page parameter (`vehicles_page`, `trailers_page`). Each list's links keep the other list's page. The owner's note read "need full system all list page in one pagination with server side pagination". It was implemented as A for this page only; see the agent note in PRODUCT_DECISIONS.md.

- `app/Services/Compliance/FleetComplianceService.php` (new): `summary()` / `withComplianceCounts()` compute each asset's status in SQL, with the same rules as `calculateCompliance()` (the oldest document of each type counts).
- `ComplianceDashboardController::fleet()`: summary cards come from SQL over every asset. Only the current page is loaded with documents and mapped with `through()`.
- `resources/views/admin/compliance/fleet.blade.php`: `links()` under each list.
- Tests: `FleetComplianceDecisionsTest.php` → `PO-01: …` (30 assets → 25 + 5; other list keeps its page; cards count all 30; SQL status = row status; tenant only). The FCMP-03 query-budget tests are still green. `FleetComplianceDashboardTest::fcmpDashboardRows()` reads `->items()`.

## Decision follow-up: PO-02

Decided 2026-10-11: option A (the driver rule), and soft-deleted assets stay excluded.

- `upload-document-modal.blade.php`: the checkbox has `value="1"`. There is a hidden `company_id`, filled from the list endpoint's `company` (the company of the asset the modal was opened for, as in the driver modal). A 422 message is now shown instead of "Network response was not ok". Closing the modal re-enables the asset select.
- `DocumentUploadController::uploadDocument()`: `upload_to_all` accepts `on`. A super-admin must send `company_id` (`Rule::requiredIf`, `Rule::exists`); tenants always use their own company. `getVehiclesList()` / `getTrailersList()` take `asset_id`, return `company`, and for a super-admin list only that company's assets.
- Tests: `FleetComplianceDecisionsTest.php` → `PO-02: …`. The exact-JSON list test now expects `company`.

## Decision follow-up: PO-22 (fleet-compliance items)

- `document_type_id` must be a type of the posted `asset_type` (`Rule::exists(...)->where('module', …)`).
- The Warning card counts warning only. A new Critical card counts missing/expired. "N Vehicles Need Attention" counts vehicles only, and the trailer badge shows its own count.
- `days_until_expiry` is an int (dashboard rows and the `VehicleDocument`/`TrailerDocument` accessor).
- "Upload to all" with no assets returns 422 before any file is stored.
- Tests: `FleetComplianceDecisionsTest.php` → `PO-22: …`.

Manual steps: none. Out-of-scope: `app/Models/VehicleDocument.php`, `app/Models/TrailerDocument.php` (int cast).

# Module report: `driver-compliance` (driver compliance dashboard & documents)

Run date: 2026-10-09, follow-up 2026-10-10 (see "Follow-up" below). Tests: `tests/Feature/Modules/DriverCompliance` and `tests/Unit/Modules/DriverCompliance` (61 tests, all passing).
Full suite: 624 passed, 10 failed. The 10 failures are the same as the baseline (`Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4, `ProfileTest` ×5), so nothing regressed. The `Drivers` module tests (80) still pass.
Larastan on the scope files: 21 errors, down from 25 before this module was started. All 21 are the existing `Relation '…' is not found` / `undefined property` kind, because the models' relations have no return types. The new errors these runs introduced were fixed, except one false positive from Laravel's own types, which is ignored on that line with a comment (see DCMP-05).

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| DCMP-01 | P1 | FIXED | `DriverComplianceFileTest.php::DCMP-01 → it stores an uploaded document on the private disk`; `…::it serves the file to the driver's tenant through the view and download routes`; `…::it lets a super-admin open any company's document`; `…::it refuses another tenant` (view, download); `…::it refuses guests and users without driver permissions`; `…::it still serves documents uploaded before the move, from the public disk`; `…::it deletes a legacy file from the public disk when the document is replaced`; `…::it returns 404 when the file is missing from both disks`; `…::it links the dashboard preview to the authorized view route, never to /storage`; `…::it moves files already on the public disk to the private disk, and can move them back` | `DriverDocumentUploadController.php`, `admin/compliance/drivers.blade.php`, migration `2026_10_09_000900_move_driver_compliance_files_to_private_disk.php` (new) |
| DCMP-02 | P1 | FIXED | `DriverComplianceSharedFileTest.php::DCMP-02 → it stays for the other drivers when one driver's document is replaced`; `…::it stays for the other drivers when one driver's document is deleted`; `…::it is deleted once the last document using it is replaced`; `…::it replaces every driver's shared file when "upload to all" runs again, and deletes the old one`; `…::it still deletes the file of a document nobody else uses` | `DriverDocumentUploadController.php` |
| DCMP-03 | P2 | FIXED | `Unit/…/DriverComplianceServiceTest.php::DCMP-03 → it is warning when the only problem is a document expiring soon`; `…::it is danger when a document is missing`; `…::it is danger when a document is expired, even if another one is only expiring`; `…::it is compliant at 100% when every document is valid`; `…::it still counts only fully valid documents in the percentage`; `DriverComplianceDashboardTest.php::DCMP-03 → it counts a driver with only an expiring document as Warning`; `…::it returns warning from the details endpoint too` | `DriverComplianceService.php` |
| DCMP-04 | P2 | FIXED | `DriverComplianceFileTest.php::DCMP-04 → it stores PDF bytes uploaded as x.html under a random .pdf name`; `…::it gives two uploads of the same client name different stored names`; regression guard `…::it rejects files whose content is not jpg, png or pdf` (html, svg named .pdf, php named .pdf) | `DriverDocumentUploadController.php` |
| DCMP-05 | P2 | FIXED (2026-10-10) | `DriverComplianceDashboardTest.php::DCMP-05 → it rejects an expiry date in the past`; `DriverCompliancePaginationTest.php::DCMP-05 → it shows 25 drivers per page and counts every active driver in the cards`; `…::it counts the cards over all pages, not just the one shown`; `…::it applies each company's switched-off types for a super-admin`; `…::it gives the same counts in SQL as the PHP calculation, at every boundary`; `…::it loads only the current page's documents`; regression guards `DriverComplianceDashboardTest.php::…::it still accepts today, or no expiry date at all` (×2), `…::it loads the dashboard in a constant number of queries` | `DriverDocumentUploadController.php`, `DriverComplianceDashboardController.php`, `DriverComplianceService.php`, `admin/compliance/drivers.blade.php` |
| DCMP-06 | P3 | FIXED | `DriverComplianceErrorsTest.php::DCMP-06 → it logs a failed driver list and returns a generic message`; `…::it logs a failed upload, returns a generic message and removes the stored file`; `…::it returns 404 for an unknown document on delete`; `…::it returns 404 for an unknown driver on upload, and stores nothing`; `…::it returns 403 and keeps the document when another tenant deletes it`; `…::it returns 403 and stores nothing when uploading for another tenant's driver`; regression guard `…::it still uploads to "all drivers" only within the tenant` | `DriverDocumentUploadController.php` |
| DCMP-07 (new) | P1 | FIXED | `DriverComplianceErrorsTest.php::DCMP-07 → it are not served or deleted for another tenant`; regression guard `…::it are not served to their own tenant either, like the rest of a deleted driver` | `DriverDocumentUploadController.php` |
| DCMP-08 (new) | P1 | FIXED | `DriverComplianceXssTest.php::DCMP-08 → it escapes every applicant- or tenant-controlled field in the details modal`; `…::it does not put the document type name in an inline onclick`; `…::it builds the upload dropdown with text options, not innerHTML`; regression guard `…::it escapes the driver name in the server-rendered list` | `admin/compliance/drivers.blade.php`, `admin/compliance/partials/driver-upload-document-modal.blade.php` |

No new factory was needed. Test fixtures are in `tests/Feature/Modules/DriverCompliance/DriverCompliance.php`.

### What changed

- **DCMP-01: private storage.**
  - Uploads are now stored on the private `local` disk (`storage/app/private/documents/drivers/…`).
  - `viewDocument()` and `downloadDocument()` stream from that disk. They fall back to the `public` disk for files the migration hasn't moved yet. Both send `X-Content-Type-Options: nosniff` and `Cache-Control: private, no-store`.
  - The dashboard's image preview used `/storage/${doc.file_path}`. It now uses `admin.compliance.driver.documents.view`.
  - The driver show page (`admin/driver/show`) already linked to the authorized routes, so it needed no change.
  - The new migration moves every file referenced by `driver_compliance_documents.file_path` from the `public` disk to `local`. The paths stay the same. Missing files are skipped, re-running it is safe, and `down()` moves the files back.
- **DCMP-02: shared file from "upload to all".**
  - "Upload to all" still stores one file and gives every driver's row the same path.
  - A file is now deleted only when no `driver_compliance_documents` row references it any more (`deleteFileIfUnused()`, which checks both disks). This also protects rows that already share a file.
  - Replaced files are deleted after the transaction commits. Before, they were deleted inside the transaction, so a failed upload rolled the rows back to a file that no longer existed.
- **DCMP-03: `warning` status.**
  - The `|| $percentage < 100` check is removed. Missing or expired documents make the status `danger`. Documents that are only expiring make it `warning`. This is the rule `ComplianceDashboardController` already uses for the fleet.
  - The percentage still counts only fully valid documents, so a Warning driver shows less than 100%.
  - Side effect: a driver whose company requires no document types now shows Compliant (0/0) instead of Critical.
- **DCMP-04: stored name.**
  - `storeAs(time().'_'.<client name>)` is replaced with `store()`, which uses `hashName()`: a random name plus the extension guessed from the content.
  - PDF bytes uploaded as `x.html` are now saved as `<random>.pdf`.
  - The `mimes:jpg,jpeg,png,pdf` whitelist was already correct. HTML, SVG and PHP content are rejected.
- **DCMP-05: expiry date and dashboard load.**
  - `expiry_date` is now `nullable|date|after_or_equal:today`. This matches the form's date picker (`min=today`) and the fleet upload. Documents with no expiry date are still accepted.
  - The dashboard is now paginated, with statuses computed in SQL (option B, decided 2026-10-10). See "Follow-up" below.
- **DCMP-06: exception text.**
  - The list, upload and delete endpoints now log failures with `Log::error` and return `Failed to load drivers.`, `Failed to upload document.` or `Failed to delete document.`
  - The driver and document lookups and the tenant checks moved out of the `try`. Before, the catch-all turned them into a 500 with the exception text ("No query results for model …", "You do not have permission …"). Now an unknown record is 404 and another tenant's record is 403, the same as the view and download routes.
  - A failed upload removes the file it stored.
- **DCMP-07 (new): a deleted driver's documents had no tenant check.**
  - `viewDocument`, `downloadDocument` and `deleteDocument` ran `authorizeCompanyAccess` only `if ($document->driver)`. A soft-deleted driver loads as `null` (DRV-08), so the check was skipped.
  - As a result, any user with `drivers.dashboard` in any company could read a deleted driver's documents by id, and any user with `drivers.edit` could delete them. Reading worked for files still on the public disk; deleting worked everywhere.
  - All three now go through `findAuthorizedDocument()`: 404 when the document or its driver is gone, 403 for another company.
- **DCMP-08 (new): stored XSS in the dashboard modals.**
  - The details modal built its HTML with template literals and `innerHTML`. That covered the driver's email, phone, licence number, licence state and status, the document type name and description, and the missing/expiring lists. The upload dropdown did the same with the driver's name.
  - Name, email, phone and licence number come from public applicants. The description comes from any tenant user, and a super-admin sees every tenant's.
  - All of these values now go through `escapeHtml()`. The dropdown is built with `new Option(...)`, which treats the name as text.
  - The image preview button used to be `onclick="previewImage('<url>', '<type name>')"`. It now uses `data-action="preview-document"`, `data-url` and `data-title`, with one delegated listener.
  - The upload modal partial is also included on `admin/driver/show`, so that page gets the dropdown fix too.

## Out-of-scope changes

- `tests/Feature/Modules/Lookups/DocumentTypeCompanyOptOutTest.php` (follow-up): `it uses each driver's own company on the driver compliance dashboard` type-hinted the view's `drivers` as an `array`. It is now a paginator, so the test reads `$drivers->items()`. What it checks (each company's switched-off types) is unchanged and still passes.

Apart from that, the only file outside the module's code is the new migration `database/migrations/2026_10_09_000900_move_driver_compliance_files_to_private_disk.php`. No routes, shared traits, middleware or config were touched.

## Deferred items (questions for the product owner)

None. Both questions were answered on 2026-10-10 and implemented (see "Follow-up" below):

1. ~~DCMP-05: should the dashboard be paginated, and how?~~ **Option B: compute the statuses in SQL and paginate everything.**
2. ~~"Upload to all drivers": which drivers?~~ **For a super-admin, only the selected company's drivers. For everyone, only active drivers.**

## Manual steps

1. **Back up `storage/app/public/documents/drivers/` and the database first.** Then run `php artisan migrate`, which runs `2026_10_09_000900_move_driver_compliance_files_to_private_disk`. It moves each referenced file from `storage/app/public/documents/drivers/` to `storage/app/private/documents/drivers/`. It is idempotent, and `down()` moves the files back.

   It was tested on SQLite with fake disks. **It has not been run on MySQL or on real storage**, so run it on staging first. If production runs on several app servers without shared storage, run it on the server that holds the uploads.
2. After the migration, `storage/app/public/documents/drivers/` should only contain files that no record references, such as old replaced files. Delete them once you have checked. Old `/storage/documents/drivers/...` links (bookmarks, emails) will return 404. That is intended.
3. No config change is needed. The private `local` disk already exists (`storage/app/private`, used by DRV-03).

## Observations (not hotspots)

- **Any document type id is accepted.** `document_type_id` uses `exists:document_types,id`, so a vehicle or trailer document type can be attached to a driver. It would never show on the dashboard. Adding `->where('module', 'driver')` would close this.
- **The upload dropdown still lists drivers who aren't active.** `getDriversList` lists every driver of the company, including drafts and pending applicants, so a single document can still be uploaded from a pending applicant's page. Only "upload to all" is limited to active drivers.
- **The fleet module has the same problems.** `DocumentUploadController` (fleet) also stores on the public disk under `time()_<client name>` and returns `$e->getMessage()`. It belongs to `fleet-compliance` (FCMP).
- **Relations have no return types.** `Driver` and `DriverComplianceDocument` relations have no `BelongsTo`/`HasMany` return types. That causes most of the Larastan errors here and in `drivers`.
- **The details JSON still includes `file_path`.** The modal uses it to pick the image preview. It is harmless now that the file is private, but the extension alone would do.

## Follow-up (2026-10-10): paginated dashboard and "upload to all"

| Item | Result | Test file::test name | Files changed |
|---|---|---|---|
| Dashboard statuses in SQL, everything paginated (DCMP-05, option B) | DONE | `DriverCompliancePaginationTest.php` (5 tests, listed under DCMP-05) | `DriverComplianceService.php`, `DriverComplianceDashboardController.php`, `admin/compliance/drivers.blade.php`; out of scope: `Lookups/DocumentTypeCompanyOptOutTest.php` |
| "Upload to all": only active drivers, one company | DONE | `DriverComplianceUploadToAllTest.php::Upload to all: which drivers → it uploads only to the tenant's active drivers`; `…::it ignores a company_id sent by a tenant`; `…::it uploads only to the chosen company's active drivers for a super-admin`; `…::it requires a super-admin to choose a company, and stores nothing without one` (×2); `…::it refuses, and stores nothing, when the company has no active driver`; regression guard `…::it still uploads a single document to a driver who is not active` | `DriverDocumentUploadController.php`, `admin/compliance/partials/driver-upload-document-modal.blade.php` |
| The form's "upload to all" checkbox works | DONE | `DriverComplianceUploadToAllTest.php::Upload to all: the modal → it posts a checkbox value the server accepts, and the company to upload to`; `…::it lists the tenant's own drivers and company`; `…::it lists, for a super-admin, only the company of the driver the modal was opened for`; `…::it gives a super-admin no company, so no "upload to all", without a driver` | `admin/compliance/partials/driver-upload-document-modal.blade.php`, `DriverDocumentUploadController.php` |

How it works:

- **Statuses in SQL.** `DriverComplianceService::withComplianceCounts()` adds three correlated subqueries to a driver query:
  - `total_docs`: active `driver` document types that the driver's company hasn't switched off in `company_document_type`;
  - `valid_docs`: those with a document that has no expiry date, or one at least 31 days away;
  - `expiring_docs`: those with a document expiring between tomorrow and 30 days from today.

  The rules are the same as `calculateCompliance()`: a document expiring today counts as expired. If a driver has two documents of one type, the oldest counts, in SQL and now explicitly in PHP too (`sortBy('id')`). A test compares the SQL and PHP counts driver by driver, at every boundary: expired, today, tomorrow, +30 and +31 days, no expiry, switched-off, inactive and vehicle types, and duplicate documents.
- **Dates.** They are compared as bound `Y-m-d` strings with `<` and `>=`, which works on MySQL `DATE` columns and on the `Y-m-d 00:00:00` text SQLite stores. No database-specific date functions are used.
- **Summary cards.** `summary()` groups the counts by status (`danger` when anything is missing or expired, `warning` when only something is expiring, otherwise `compliant`) in one query over every active driver the user may see, across all pages.
- **List.** It shows 25 drivers per page, ordered by first name, last name and id, with Laravel's pagination links under the list. Only the current page's drivers are loaded with their documents. The per-document rows are still built by `calculateCompliance()`.
- **MySQL check.** The generated SQL was run read-only on the local MySQL 9.3 database (`driver_qualification`). It returned the same counts as the PHP calculation for the driver there. The local database hasn't run `2026_10_09_000800` (`drivers.deleted_at`) yet, so that check used `withTrashed()`.
- **Larastan.** `through()` is reported as "unresolvable type" because Laravel types it with `@phpstan-this-out static<…>`. That one line has a `@phpstan-ignore argument.unresolvableType` with the reason.
- **"Upload to all": which drivers.** It now uploads only to drivers with status `active` in one company. Drafts, pending, rejected and withdrawn drivers are skipped, and so are deleted ones.
  - A tenant always uploads to their own company. A `company_id` they send is ignored.
  - A super-admin must send `company_id`, which must be an existing company. Without it the response is 422 and nothing is stored.
  - If the company has no active driver, the response is 422 "There are no active drivers to upload this document to." and nothing is stored.
  - A single upload still works for any driver of the company.
- **The modal.**
  - The checkbox now has `value="1"`.
  - The modal asks `drivers/list` for the driver it was opened for (`driver_id`). The response includes the company (`company: {id, name}`), which fills a hidden `company_id` field and the label "Upload to All Active Drivers of <company>". The label is set with `textContent`.
  - For a super-admin, the driver dropdown now lists only that company's drivers. Without a driver there is no company, so the checkbox is disabled.
  - The partial is also used on `admin/driver/show`, which opens the modal for that driver, so it works there unchanged.

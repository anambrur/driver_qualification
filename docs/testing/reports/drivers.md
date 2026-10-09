# Module report: `drivers` (driver management, admin)

Run date: 2026-10-09. Tests: `tests/Feature/Modules/Drivers` (80 tests, all passing, including the follow-up below).
Full suite: 563 passed, 10 failed. The 10 failures are the same as the baseline (`Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4, `ProfileTest` ×5), so nothing regressed.
Larastan on the scope files: 41 errors, all already there before this run (mostly `Relation '…' is not found in App\Models\Driver`, because the relations have no return types). None are on lines changed here.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| DRV-01 | P1 | FIXED | `DriverXssTest.php::DRV-01 → it puts the name in an escaped data attribute instead of inline onclick` (3 payloads); `…::it shows the name in the confirm dialog as text, not HTML` | `DriverController.php`, `admin/driver/index.blade.php` |
| DRV-02 | P1 | FIXED | `DriverSsnEncryptionTest.php::DRV-02 → it encrypts the SSN when a tenant creates a driver`; `…::when a tenant edits a driver`; `…::the SSN an applicant enters on the public step 1`; `…::it encrypts the SSNs already stored in plain text, once, and can undo it`; regression guard `…::it still shows only the last four digits on the driver page` | `app/Models/Driver.php`, migration `2026_10_09_000600_encrypt_driver_ssn.php` (new) |
| DRV-03 | P1 | FIXED | `DriverFilePrivacyTest.php::DRV-03 → it stores wizard uploads on the private disk`; `…::it stores the photo from the admin create form on the private disk`; `…::it stores public application uploads on the private disk`; `…::it serves each file to the driver's tenant` (×5 fields); `…::it lets a super-admin open any company's file`; `…::it refuses another tenant` (×5); `…::it refuses guests and users without driver permissions`; `…::it returns 404 for unknown fields and missing files`; `…::it still serves files uploaded before the move, from the public disk`; `…::it links the admin pages to the authorized route, never to /storage`; `…::it serves an applicant their own uploads, and nobody else`; `…::it moves files already on the public disk to the private disk, and can move them back` | `DriverDocumentWizardService.php`, `DriverCrudService.php`, `DriverController.php`, views `admin/driver/{license,medical-card,forfeiture,show,edit,index}`, `components/driver-photo-upload`, migration `2026_10_09_000700_move_driver_files_to_private_disk.php` (new); out of scope: `routes/web.php`, `ApplicationFormController.php`, `application/steps/step{2,3,4}` |
| DRV-04 | P2 | DISMISSED | `DriverFilePrivacyTest.php::DRV-04/DRV-05 → it rejects an SVG photo`; `…::it stores the photo under a random name with the extension of its content` | none |
| DRV-05 | P2 | DISMISSED | `DriverFilePrivacyTest.php::DRV-04/DRV-05 → it gives two photos uploaded in the same second different names` | none |
| DRV-06 | P2 | FIXED | `DriverStatusTest.php::DRV-06 → it ignores a status sent with the edit form` (active, rejected, draft, approved); `…::it no longer has a separate status endpoint (removed 2026-10-09)`; regression guards `…::it still saves the edit form without a status field`, `…::it still hires a pending driver through the hire workflow` | `UpdateDriverRequest.php`, `DriverController.php`, `routes/web.php` |
| DRV-07 | P2 | FIXED | `DriverIndexTest.php::DRV-07 → it falls back to a safe direction for an invalid value` (3 values); `…::it does not crash when the sort column is missing`; regression guard `…::it still sorts by name in both directions` | `DriverController.php` |
| DRV-08 | P2 | FIXED | `DriverSoftDeleteTest.php::DRV-08 → it soft-deletes the driver and keeps its records and files`; `…::it hides a deleted driver from the list, the counts and every driver page`; `…::it soft-deletes the driver's asset groups, as the old cascade removed them`; `…::it does not let a deleted driver be put in an asset group`; `…::it explains, instead of failing on insert, when the email belongs to a deleted driver`; `…::it still lets a driver keep their own email on edit, but not take another driver's`; `…::it lets a super-admin delete any company's driver, softly`; `DriverUpdateDestroyTest.php::DRV-08 → it keeps the signature when the driver had no violations`; `…::it keeps the signature on every violation row`; `…::it returns 404, not a 500 with the exception text, for an unknown driver`; `…::it logs a failed delete and returns a generic message`; `…::it still returns 403 for another tenant's driver and keeps it`; regression guard `…::it still deletes the tenant's own driver` | `DriverCrudService.php`, `DriverController.php`, `app/Models/Driver.php`, `StoreDriverRequest.php`, `UpdateDriverRequest.php`, migration `2026_10_09_000800_add_soft_deletes_to_drivers_table.php` (new); out of scope: `AssetGroupController.php` |
| DRV-09 | P3 | FIXED | `DriverRouteParamsTest.php::DRV-09 → it returns 404 for a non-numeric driver id on every wizard page` (×9); regression guards `…::it returns 404 for a non-numeric id on the driver pages`, `…::it still renders every wizard page for the tenant's own driver` (×9), `…::it renders the edit page when there is no US country row` | `DriverController.php` |
| DRV-10 | P2 | FIXED | `DriverIndexTest.php::DRV-10 → it counts drivers by status in one query`; `…::it shows a super-admin every status, drafts included`; `…::it does not grow with the number of drivers in the ajax list` | `DriverController.php` |

No new factory was needed. Test fixtures are in `tests/Feature/Modules/Drivers/DriverAdmin.php`.

### What changed

- **DRV-01: XSS in the delete button.**
  - The list's delete button was `onclick="deleteDriver(id, '<addslashes(name)>')"`. `addslashes` doesn't stop HTML entities: a name like `&#39;);alert(1);//` became a `'` once the browser decoded the attribute.
  - The button now carries `data-action="delete-driver"`, `data-driver-id` and `data-driver-name="<e(name)>"`. One delegated jQuery listener calls the existing `deleteDriver()`. The listener also works for the mobile cards, which reuse the same HTML.
  - The SweetAlert confirm built its message with `html:` and the raw name. The name now goes through `escapeHtml()` first.
  - The other DataTables columns were already escaped by yajra (`escape => '*'`).
- **DRV-02: SSN encryption.**
  - `Driver` now has `'ssn' => 'encrypted'`. Every write path (admin create/edit, public step 1, factories) goes through the model, so all of them encrypt.
  - The show page still masks the SSN to `***-**-6789`.
  - The new migration encrypts existing plain-text SSNs. It skips values that already decrypt, so re-running it is safe. `down()` decrypts them again.
  - The column is `varchar(255)`, which is large enough for the encrypted value.
- **DRV-03: private storage.**
  - The photo, licence front/back, medical card and forfeiture document are now stored on the private `local` disk (`storage/app/private`), under the same relative paths (`images/drivers/…`, `images/documents/…`).
  - `DriverDocumentWizardService::fileResponse()` whitelists the 5 fields and streams the file with `X-Content-Type-Options: nosniff` and `Cache-Control: private, no-store`.
  - The service is called from two routes:
    - `GET /admin/driver/{id}/file/{field}` (`admin.driver.file`). It needs `permission:drivers.view|drivers.create|drivers.edit` and uses `authorizeCompanyAccess`, so another tenant gets 403, the same as the rest of this controller.
    - `GET /{slug}/application/file/{driver_id}/{field}` (`public.application.file`). It needs the applicant's own session (`applicationDriver()`), so the applicant can still see the preview of what they uploaded.
  - All admin and public views now link to these routes instead of `/storage/...`.
  - Files uploaded before this change are still served from the public disk until the migration moves them.
  - Replacing a file deletes the old one from both disks.
- **DRV-04 / DRV-05: dismissed.**
  - SVG is already rejected, because Laravel 12's `image` rule excludes SVG unless `image:allow_svg` is set. The `mimes:…,svg` part is never reached.
  - The client-extension and same-second name problems were already fixed by APP-10 (`hashName()`). The tests stay as regression guards.
- **DRV-06: status bypass.**
  - `UpdateDriverRequest` no longer has a `status` rule, so `validated()` never contains `status` and the service keeps the current one. The edit form only re-posted the current status in a hidden field, so the UI is unchanged.
  - `POST /admin/driver/{id}/status` (`updateStatus`) is removed (follow-up below). Hiring and rejecting only go through the hire workflow, which stores the reason, the date and `action_by`, and sends the email.
- **DRV-07: sort direction.**
  - `order[0][dir]` is now `desc` only when it is exactly `desc` (case-insensitive), and `asc` otherwise. The column index is cast to `int`.
  - It turned out yajra catches the exception, so this was not a 500. It returned HTTP 200 with an empty list and the exception message in `error` (see Observations).
- **DRV-08: update and destroy.**
  - `update()` still deletes and re-creates the child rows, but it now copies the applicant's step-5 `violation_record_signature`/`_date_signed` onto the new violation rows. Before this, any save of the edit form wiped the signature shown on the step-5 page.
  - In `destroy()`, the lookup and tenant check moved out of the `try`. A missing driver is now 404 instead of 500, and another tenant's driver is now 403 instead of 500. Before this, the catch-all turned `abort(403)` into a 500 with `Error deleting driver: …`.
  - Real failures are logged and return a generic message.
  - Delete is now a soft delete (follow-up below).
- **DRV-09: non-numeric ids.**
  - `loadWizardDriver()` now accepts the route string and returns 404 unless it is all digits. The 9 wizard pages returned a 500 `TypeError` for `/admin/driver/license/abc`.
  - The suspected crash in `edit()` when there is no `US` country does not happen: `->first()->id ?? 1` has `isset` semantics. A regression test covers it.
- **DRV-10: status counts.**
  - The 6 `COUNT` queries are now one `GROUP BY status` query.
  - It also moved below the ajax branch. Before, it ran on every DataTables draw, where its result was thrown away.
  - The ajax list keeps a constant number of queries as drivers are added (`Perf::assertConstantQueries`).

## Out-of-scope changes

- `routes/web.php`:
  - added `admin.driver.file` in the admin driver group;
  - added `public.application.file` in the public application group;
  - removed `admin.driver.update.status` (follow-up).
- `app/Http/Controllers/AssetGroupController.php` (follow-up): `driver_id` on store and update now uses `Rule::exists(...)->withoutTrashed()`, so a deleted driver can't be put in a group.
- `app/Http/Controllers/ApplicationFormController.php`: a new `file()` action. It is 6 lines and reuses `activeCompany()` and `applicationDriver()`. The public steps 2–4 show the applicant's uploads, so without it those previews would break once the files are private.
- `resources/views/application/steps/step2-license.blade.php`, `step3-medical-card.blade.php`, `step4-forfeiture.blade.php`: `Storage::url(…)` replaced with `route('public.application.file', …)`.
- `resources/views/components/driver-photo-upload.blade.php`: the prop is now `existing-photo-url` (a URL) instead of `existing-photo` (a storage path). It is used by admin create/edit and public step 1, and only admin edit passed a value.
- `tests/Feature/Modules/PublicApplication/UploadNamingTest.php` (APP-10): it now asserts that files exist on the `local` disk instead of `public`, because that is the intended change.

## Deferred items (questions for the product owner)

No hotspot is deferred. Both questions were answered on 2026-10-09 and implemented (see "Follow-up" below):

1. ~~DRV-08: what should deleting a driver do?~~ **(a) Soft delete.**
2. ~~DRV-06: should the separate status endpoint stay?~~ **Remove it.**

## Manual steps

1. **Back up the database and `storage/app/public/images/` first.** Then run `php artisan migrate`, which runs two new migrations:
   - `2026_10_09_000600_encrypt_driver_ssn` encrypts every plain-text `drivers.ssn` with `APP_KEY`. It is idempotent, and `down()` decrypts.
   - `2026_10_09_000700_move_driver_files_to_private_disk` moves every file referenced by `drivers.photo` and `driver_documents.{license_front,license_back,medical_card,forfeiture_document}` from `storage/app/public` to `storage/app/private`. The paths stay the same, missing files are skipped, and `down()` moves them back.

   Both were tested on SQLite with fake disks. **Neither has been run on MySQL or on real storage**, so run them on staging first. If production uses several app servers without shared storage, run the file move on the server that holds the uploads.
2. **`APP_KEY` must never be lost or changed by accident.** SSNs can only be read with it. To rotate the key, put the old one in `APP_PREVIOUS_KEYS`.
3. The follow-up adds a third migration, `2026_10_09_000800_add_soft_deletes_to_drivers_table`, which adds a nullable `drivers.deleted_at`. It is schema only; no data changes.
4. After the migration, `storage/app/public/images/documents/` and `storage/app/public/images/drivers/` should contain only files that no record references. Delete them once you have checked. Old `/storage/images/...` links (bookmarks, emails) will return 404. That is intended.

## Follow-up (2026-10-09): soft delete and status endpoint

| Item | Result | Test file::test name | Files changed |
|---|---|---|---|
| Deleting a driver is a soft delete | DONE | `DriverSoftDeleteTest.php` (7 tests, listed under DRV-08) | migration `2026_10_09_000800`, `Driver.php`, `DriverController.php`, `StoreDriverRequest.php`, `UpdateDriverRequest.php`, `AssetGroupController.php` |
| Status endpoint removed | DONE | `DriverStatusTest.php::DRV-06 → it no longer has a separate status endpoint (removed 2026-10-09)` | `routes/web.php`, `DriverController.php` |

How it works:

- **What a delete does now.**
  - `Driver` uses `SoftDeletes`. `DELETE /admin/driver/{id}` sets `deleted_at`.
  - Nothing else is removed. The licences, experiences, accidents, violations, forfeitures, employment records, residences, `driver_documents`, compliance documents and the private files all stay, so the DQ file is kept for retention.
  - The database cascades only fire on a real delete, for example when a whole company is deleted.
- **Where a deleted driver disappears.**
  - All Eloquent queries skip deleted drivers. That covers the list, the status counts, the show, edit and wizard pages, the file route, `hire-status` (route model binding), the dashboard and compliance dashboards, the asset-group driver dropdown and the public application lookups. Each of these now returns 404 or leaves the driver out.
  - A deleted driver can't be deleted again (404).
- **Asset groups.** The old hard delete cascaded to `asset_groups`. Now the driver's asset groups are soft-deleted in the same transaction, so the result looks the same and the groups can still be restored from the Deleted filter on the asset groups page. The asset-group form also rejects a deleted driver's id.
- **Email.** The `(company_id, email)` unique index still counts deleted drivers. The admin create and edit forms now check it in validation and say so ("…including deleted drivers"). Before, they failed on insert with a generic error. The public step 1 already had this check.
- **Restore.** There is no restore button for drivers (none was requested). A super-admin can restore a driver in tinker with `Driver::withTrashed()->find($id)->restore()`. Their asset groups must then be restored separately.
- **Status endpoint.** `updateStatus()` and the `admin.driver.update.status` route are removed. Nothing in the UI called them. A driver's status now changes only through:
  - the application flow (draft → pending, withdrawn);
  - the hire workflow (pending → active or rejected).

  There is still no way to mark a hired driver `inactive`. If that is needed, it should be its own audited action.

## Observations (not hotspots)

- **yajra DataTables shows exception messages to the browser.** `config/datatables.php` is not published, so `error` is `null`. On any exception, every DataTables endpoint in the app returns HTTP 200 with `"error": "Exception Message: …"`. Setting `DATATABLES_ERROR` (or publishing the config with a generic message) would fix this app-wide. It belongs to `access-control`.
- **404 JSON names the model.** Laravel turns `findOrFail` into a 404 whose JSON message is `No query results for model [App\Models\Driver] 123`, even with debug off. This is framework-wide (`bootstrap/app.php` could override it).
- **The edit form puts the full SSN in the page HTML.** It is in the `value` of the SSN input (`edit.blade.php:215`). It is encrypted at rest now, but it is still sent to the browser on every edit page view. Consider masking it and only overwriting the SSN when a new one is typed.
- **A deleted driver's compliance documents** are hidden on the dashboards (`whereHas('driver')`). But `DriverDocumentUploadController` loads a document by id with `->driver` and then reads `company_id`. For a deleted driver's document id that is `null`, so the action fails instead of returning 404. This is in the `driver-compliance` module.
- **Wizard store actions check `driver_id` against all drivers.** They use bare `exists:drivers,id` instead of `Rule::exists(...)->where('company_id', …)`. The tenant check in `loadWizardDriver()` still returns 403 before anything is written. But `licenseStore` first reads the other tenant's `DriverDocument` to decide whether the images are required.
- **The photo component still mentions SVG.** It says "PNG, JPG, GIF, SVG" and its `accept` includes `image/svg+xml`, although the server rejects SVG.
- **`update()` still rewrites every child row on each save.** Ids and `created_at` change every time. The signature was the only data lost. Diffing the rows instead would be a larger refactor.
- **Company policy PDFs are still public.** They are served from `/storage/...` on the wizard steps 9 and 10, and they belong to the `companies` module.

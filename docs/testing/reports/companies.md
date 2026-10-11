# Module report: `companies` (Company accounts & policy PDFs)

Run date: 2026-10-10. Tests: `tests/Feature/Modules/Companies` (25 tests, 82 assertions), all passing.

**Full suite:** 891 passed, 10 failed. The baseline before any change was 866 passed, 10 failed. The same 10 tests fail in both runs: `Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4 and `ProfileTest` ×5. Nothing regressed.

**Larastan** on the scope files plus the new migration: 0 errors. Before this run there were 6, all `Relation 'user' is not found in App\Models\Company`. They were fixed by adding a `BelongsTo` return type to `Company::user()`.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| CMP-01 | P1 | FIXED | `CompanyListXssTest.php::escapes the company name in the logo alt attribute`; `::passes the apply URL through a data attribute, not an inline onclick`; regression guard `::escapes the company name, phone and city columns` (passed before: Yajra escapes non-raw columns) | `app/Http/Controllers/CompanyController.php`, `resources/views/admin/settings/company/index.blade.php` |
| CMP-02 | P2 | FIXED | `CompanyOwnerUpdateTest.php::does not let a company owner change their own status`; `::does not let a company owner change the account email`; `::rejects an email that already belongs to another user`; `::rejects a new company whose email already belongs to a user`; regression guards `::still lets a company owner update the other profile fields`, `::does not let a company owner edit another company`, `::lets a super-admin change status and email` | `app/Http/Controllers/CompanyController.php`, `resources/views/admin/settings/company/edit.blade.php` |
| CMP-03 | P2 | DEFERRED | `CompanyDestroyTest.php::does not let a company owner delete a company`, `::does not let a plain user with companies.view delete a company` (guards, pass); `::currently deletes the owner and cascades to the company drivers` (documents current behaviour) | none |
| CMP-04 | P2 | FIXED | `CompanyLogoAndSlugTest.php::rejects an SVG logo on create`; `::rejects an SVG logo on update`; `::rejects an HTML file renamed to .png`; `::stores an uploaded logo under a content-derived name`; `::stores a logo on create under a content-derived name`; `::enforces unique slugs in the database`; regression guard `::gives a new company a unique slug when the name is taken` | `app/Http/Controllers/CompanyController.php`, `database/migrations/2026_10_10_000400_make_company_slug_unique.php` |
| CMP-05 | P3 | FIXED (raw exception) / DEFERRED (global vs per-company PolicyPdf) | `PolicyPdfTest.php::does not leak the exception message when the upload fails`; `::stores policy PDFs under content-derived names`; `::replaces the old file when a new policy PDF is uploaded`; regression guards `::rejects a non-PDF policy upload`, `::does not let a company owner upload global policy PDFs` | `app/Http/Controllers/CompanyController.php` |

The shared fixtures are in `tests/Feature/Modules/Companies/Companies.php`. The module section doesn't list any missing factories, so none were added.

### What changed

- **CMP-01:**
  - In the DataTables `logo` column, `company_name` (which a tenant can set) and the logo URL are now escaped with `e()`.
  - The apply-URL column escapes the URL.
  - The copy button now carries the URL in `data-url` instead of `onclick="copyApplyUrl('…')"`. A delegated jQuery listener in `index.blade.php` calls `copyApplyUrl()` with that value, and the "copied" icon lookup now matches on `data-url`.
  - The delete button's `onclick="deleteCompany(<int id>)"` only ever contains an integer, so I left it as is.
- **CMP-02:**
  - `update()` only applies `email` and `status` (on both the company and the owner user) when the actor is a super-admin. For everyone else the stored values are kept, and those two fields aren't required.
  - On the edit form, owners now see the email field as read-only and the status select as disabled.
  - Company owners still change their login email through the Profile page, which resets email verification.
  - Extra validation:
    - `email` must also be unique in `users`: `unique:users,email` on create, and `Rule::unique('users')->ignore($company->user_id)` on update. Before this, a duplicate email caused a DB exception and a generic toast.
    - Validation failures now also flash the validator errors (`withErrors($validator)`), not just toasts.
- **CMP-04:**
  - The logo `mimes` rule no longer allows `svg`.
  - Logos are stored as `$file->hashName()` (content-derived extension) instead of `time()_slug.<client extension>`.
  - New migration: `companies.slug` changes from `TEXT` to `VARCHAR(300)` and gets a unique index. It never rewrites data. If duplicate slugs or slugs longer than 300 characters already exist, it stops with an error that lists them.
- **CMP-05:**
  - The upload error path now shows `Upload failed. Please try again.` instead of `$e->getMessage()`. The exception is still logged.
  - Policy PDFs are stored with `hashName()`.
  - Old files are now deleted through `Storage::disk('public')`. Before, `File::delete(storage_path(...))` was used; that behaves the same in production and also works with the fake disk in tests.

## Out-of-scope changes

- `database/migrations/2026_10_10_000400_make_company_slug_unique.php`: a new migration. The module's Scope doesn't list migrations, but the CMP-04 fix needs one.
- No changes to `routes/web.php`, middleware, shared traits or config.

## Deferred items (questions for the product owner)

1. **CMP-03: deleting a company.** "When a super-admin deletes a company, the code hard-deletes the company **and its owner user**. The database cascades then permanently delete all of that company's drivers (with their licences, documents, employment records), vehicles, trailers, service logs, subscriptions and payment records. Uploaded files are left orphaned on disk. Is that intended? Or should deleting a company (a) only deactivate it (`status = inactive`), (b) soft-delete it with a restore window, or (c) hard-delete but also remove its stored files? Also, the delete button is shown for the super-admin's own company too, and deleting it deletes the super-admin account. Should that be blocked?"
2. **CMP-05: policy PDFs scope.** "The Alcohol & Drug Test policy and the General Work policy PDFs are stored as one global record (`PolicyPdf::first()`), editable only by super-admins and shared by every company. Should each company upload its own policies (per-tenant `company_id` on `policy_pdfs`), or is one platform-wide policy set correct?"

## Observations (not fixed)

- The edit form has a "Remove current logo" checkbox (`remove_logo`), but `update()` ignores it. Replacing a logo also leaves the old file on disk.
- `RegisteredUserController` (auth module) generates slugs as `Str::slug(name).'-'.Str::random(4)` without checking for uniqueness. With the new unique index, a rare collision now fails registration with the existing generic "registration failed" message instead of silently creating a duplicate slug. Worth a uniqueness loop when the `auth` module is worked on.
- Company and user emails can drift apart after an owner changes their login email on the Profile page, because `companies.email` isn't updated there. This behaviour was already there.

## Manual steps

- Run `php artisan migrate` to apply `2026_10_10_000400_make_company_slug_unique`. Before that, check production for duplicate slugs: `SELECT slug, COUNT(*) FROM companies WHERE slug IS NOT NULL GROUP BY slug HAVING COUNT(*) > 1;`. If the migration finds any, it aborts and lists them; rename them by hand, then rerun.
- No config changes.

## Decision follow-up: PO-13

Decided 2026-10-11: option A, and Q2 = yes.

- `CompanyController::destroy()` sets the company and its owner `inactive` (login and `EnsureUserIsActive` refuse inactive users). Nothing is deleted. A super-admin's own company returns 422. A missing company is now 404 (it was 500).
- List: the button is "Deactivate" (ban icon). It is not shown for the admin's own company or for inactive companies. A super-admin reactivates a company from Edit. The JS confirm/result texts were updated.
- Tests: `CompanyDestroyTest.php`. The pinning test became `deactivates the company and its owner and keeps every record (decided PO-13, 2026-10-11)`; added "locks the deactivated owner out", "refuses … own company", and "offers Deactivate …".

## Decision follow-up: PO-14

Decided 2026-10-11: option A.

- New migration `2026_10_11_000200_add_company_id_to_policy_pdfs.php`: nullable `company_id` FK (cascade), unique. Existing rows keep `null` and are the platform set. Re-runnable, with a `down()`.
- `PolicyPdf::resolveFor($companyId)`: each policy is the company's own file, or the platform file. It is used in `ApplicationFormController` (steps 9/10, the application's company) and `DriverController` (the driver's company).
- Who edits: a super-admin edits the platform set. A non-super-admin with a company (company owners, via the existing `companies.edit` permission) edits their own set. A user with `policy-pdf.edit` and no company edits the platform set, as before. Routes: `permission:policy-pdf.view|companies.edit` and `permission:policy-pdf.edit|companies.edit`. No new permission was added and `PermissionSeeder` was not changed.
- Files stay on the public disk under `policy_pdfs/{company_id}/<hashName>`, because job applicants (guests) open them on the public form. The settings page now lists the user's existing PDFs (`$existingPolicies` was never passed before). Owners get a "Policy PDFs" link under ACCOUNT in the sidebar.
- Tests: `PolicyPdfTest.php` ("does not let a company owner upload global policy PDFs" now asserts that the upload goes to the owner's own row) and `CompanyDecisionsTest.php` → `PO-14: …`.
- Out-of-scope: `routes/web.php`, `ApplicationFormController.php`, `DriverController.php`, `resources/views/partials/sidebar.blade.php`.

## Decision follow-up: PO-22 (companies items)

- `update()` honours "Remove current logo", and deletes the old logo file after commit when it is replaced or removed.
- `ProfileController::update()` copies a changed login email to `companies.email` for the user's company. `ProfileUpdateRequest` checks `companies.email` uniqueness, ignoring the user's own company.
- Tests: `CompanyDecisionsTest.php` → `PO-22: …`.
- Also fixed a flaky assertion in `CompanyLogoAndSlugTest.php`: a random 40-character name could contain "my". It now checks "my logo".

Manual steps: run migration `2026_10_11_000200` (staging first).

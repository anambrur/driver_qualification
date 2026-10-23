# Agent prompts: Phase 1 (Quick wins)

Phase 0 (foundation) is done: WP1–WP9 merged, UI phase 2 and follow-ups merged, main at `67bb97c` (21 Oct 2026), full MySQL suite green.
Phase 1 delivers roadmap tasks 1.1–1.6 ([13](../13-implementation-roadmap.md#phase-1-quick-wins)).

One package = one branch = one fresh Claude session. Run them one at a time, in this order.

## How to run a package

1. `git status` is clean and you are on `main`.
2. Start `claude`, press Shift+Tab for Plan mode, paste the prompt block from the package file.
3. Read the plan. Answer each **PENDING**, or reply "ok, use the recommended defaults".
4. Approve. The agent then runs AUTO-FINISH (below) on its own.
5. Run the **deploy steps** from its final report on your local database. Push when you decide; agents never push.

If a gate fails, the agent stays on the branch and reports. Fix it with a follow-up message, then say "re-run AUTO-FINISH".

## Order

| # | File | Roadmap | Requirement | Depends on | Size |
|---|---|---|---|---|---|
| P1-1 | [p1-1-plates.md](p1-1-plates.md) | 1.5 | 3.5 plate and photo | — | S |
| P1-2 | [p1-2-user-grants.md](p1-2-user-grants.md) | 1.6 | 1.8 per-user permissions | — | S |
| P1-3 | [p1-3-social-login.md](p1-3-social-login.md) | 1.1 | 1.4 Google and Facebook | — | M |
| P1-4 | [p1-4-folders-documents.md](p1-4-folders-documents.md) | 1.2 | 2.1–2.3 folders, upload | — | L |
| P1-5 | [p1-5-image-to-pdf.md](p1-5-image-to-pdf.md) | 1.3 | 2.4 photo to PDF | P1-4 | M |
| P1-6 | [p1-6-zip-export.md](p1-6-zip-export.md) | 1.4 | 2.5 ZIP driver and company | P1-4, P1-5 | L |
| P1-7 | [p1-7-acceptance.md](p1-7-acceptance.md) | — | Phase 1 acceptance, docs | all | S |

P1-1 to P1-3 are independent and small, so they come first. P1-4 → P1-5 → P1-6 is a chain.

## Already in place (verified 21 Oct 2026)

- [V] `vehicles` and `trailers` have `plate_number`, `plate_state_code`, `photo_file_id` with the cFK to `stored_files` (create migrations, lines 25–27). No request, form, table or detail page uses them. `AssetTransferService` already copies the photo (:128, :179–180).
- [V] Features `document_folders` and `zip_export` exist (`app/Features/Feature.php:21-22`). Permissions `folders.view`, `folders.manage`, `documents.upload`, `documents.delete`, `exports.create`, `exports.download` exist (`app/Authorization/PermissionCatalogue.php:75-81`), and the role templates hold them.
- [V] Per-user grants backend: `UserPermissionController`, `RoleAssignmentService` grant and revoke with escalation checks, routes `web.php:192-194`. No UI.
- [V] `StoredFileService::store(UploadedFile, $area)` must run inside a transaction, checks `Limits::assertCanStore` and increments `companies.storage_bytes`; `response()` serves downloads. There is no method yet for generated content (PDF, ZIP).
- [V] `RegisteredUserController::store` uses `EmailIdentityService::claim` + `CompanyProvisioner::provision`.
- [V] Job middleware `App\Jobs\Middleware\TenantAware`; tenant trait `App\Tenancy\BelongsToCompany`; flash helper `App\Support\Ui\Toast`.
- [V] Missing: tables `social_accounts`, `folders`, `documents`, `exports`; `laravel/socialite`; any file job.
- [V] Local PHP has gd, imagick, exif, zip; dompdf 3.1 is installed. [A] Production PHP extensions and a running queue worker must be confirmed (PO-20).

## Rules for every session

- Read `CLAUDE.md` and load the skills the package names.
- **Plan first.** List every PENDING with a recommended default and wait for explicit approval. If the docs conflict with the code or with each other, stop and ask.
- **Schema.** New tables get their own create migration and follow [08](../08-database-schema.md) §1: cFKs, `UNIQUE(company_id, id)`, `CheckConstraints::add()` as the last statement, RESTRICT on columns used in a CHECK. Never alter a table after its create (SQLite drops CHECKs); a MySQL-only FK migration is the accepted exception (the companies owner FK precedent).
- **Tenancy.** Tenant models use `BelongsToCompany`. Every route has auth, a permission and the feature middleware. Add every new tenant route with an id to the `IsolationMatrixTest` fixtures, and every new public route to `ACL_PUBLIC_ROUTES` in `RouteGuardMatrixTest` with its reason. Creating tenant rows in tests needs `TenantContext::run()`.
- **Permissions.** A new permission goes into `PermissionCatalogue` and its role templates, and the deploy steps then list `PermissionSeeder` and `php artisan roles:sync-company`.
- **Files.** Always through `StoredFileService` on the private disk; downloads through an authorized controller; never a public URL.
- **Jobs.** `TenantAware` middleware, carry `companyId`, dispatch after commit, named queues per [12](../12-performance-plan.md) §3.
- **Audit.** Write the events [08](../08-database-schema.md) §10 requires: deletes, exports and ZIP downloads, DQ-document downloads.
- **UI.** `x-ui` components (Basecoat), yajra tables for lists that grow, Lucide icons, `Toast`, `data-confirm` for destructive actions, theme tokens only (`ThemeTokensTest`). Rebuild and commit `public/build` when assets change.
- **SEO.** Every new app page is private (noindex); load `seo-checklist`.
- **Read-only access state.** Write permissions (`is_write`) are refused automatically; test it for each new write route.
- **Afterwards.** Record each decision taken in [14](../14-open-questions.md) and the decision log in [../README.md](../README.md).

## AUTO-FINISH (every package ends with this)

```
AUTO-FINISH (do all of this yourself after I approve the plan; do not ask me to run commands):
0. Start: if on main, create branch p1/<package name>; if the working tree is dirty, stop and tell me.
1. Implement the approved plan.
2. Gates, on changed files only, fixing and re-running until green:
   a. vendor/bin/pint --dirty
   b. vendor/bin/phpstan analyse --memory-limit=1G <changed app/ files>  (no new errors compared with main)
   c. php artisan test --filter=<area of this package>
   d. Full suite on MySQL only, run alone, and do not edit files while it runs:
      env DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_USERNAME=root DB_PASSWORD= DB_DATABASE=dq_ui_scratch php artisan test
      plus --group=mysql when the package touches limits, locking or concurrency.
   e. If git status shows the tracked SQLite file `driver_qualification` modified, restore it.
3. Run the code-review skill at high effort on the diff. Fix CONFIRMED findings, re-run step 2. List PLAUSIBLE ones for me.
4. Commit with a clear message (end with the Co-Authored-By line from the session's attribution reminder). Never push.
5. Merge into main locally (git checkout main && git merge --no-ff <branch>) ONLY if every gate in steps 2-3 passed. Otherwise stay on the branch and report what failed, with the test output.
6. Final report: gates with evidence, decisions applied, PENDING items left, deploy steps (migrate, seeders, roles:sync-company, commands, queue and scheduler, env keys), the next package.
Never use --no-verify, skip tests, or weaken assertions to get green.
```

## Deploy checklist for the whole phase

Collected from the package reports; the full list is confirmed in P1-7.

- `composer install` (Socialite), `php artisan migrate`.
- `php artisan folders:ensure-system` once, for drivers, vehicles and trailers that already exist (P1-4).
- `PermissionSeeder` + `php artisan roles:sync-company` if any package added a permission.
- A queue worker that serves the `default` queue (P1-5, photo to PDF; `memory_limit` at least 512M), a worker on the `exports` connection (`php artisan queue:work exports --queue=exports --timeout=900 --memory=512`, P1-6), and the scheduler every minute for `exports:prune` (P1-6).
- Env: `GOOGLE_*`, `FACEBOOK_*` (P1-3); optional `DOCUMENTS_CONVERT_IMAGES` (default on, P1-5); optional `EXPORTS_QUEUE_DRIVER` (default `database`, P1-6). PHP: gd with WebP support, exif (P1-5), zip.
- [L] Google OAuth consent screen and Facebook app review, with a data-deletion instructions page (P1-3).

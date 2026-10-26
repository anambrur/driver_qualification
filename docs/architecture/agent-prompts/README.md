# Agent prompts: Phase 2 (Trips and loads, web)

Phase 0 (foundation) and Phase 1 (quick wins) are done: Phase 1 merged and accepted on 23 Oct 2026 (main `2b44dc9`, MySQL suite 2,425 passed), then the pre-Phase-2 work on `p2prep/*` (co-driver dropped, registration time zone, docs pass, site-settings UI, transfer back, company purge, driver Terminate, applications closed) merged by `3182e1f`.
Phase 2 delivers roadmap tasks 2.1–2.7 ([13](../13-implementation-roadmap.md#phase-2-trips-and-loads-web)). The plan was approved on 23 Oct 2026 with the recommended defaults ([14](../14-open-questions.md) §2 "Maps (Phase 2 plan)" and "Phase 2 plan").

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
| P2-1 | [p2-1-shippers-map.md](p2-1-shippers-map.md) | 2.1 | 4.4 shippers; the address and map picker for 6.1 | — | M |
| P2-2 | [p2-2-trips.md](p2-2-trips.md) | 2.2 | 4.1–4.3, 6.1, 6.3 trips, numbers, stops, trip folder | P2-1 | L |
| P2-3 | [p2-3-assignment-lifecycle.md](p2-3-assignment-lifecycle.md) | 2.3 | 4.5, 4.6 assignment, equipment, start, complete | P2-2 | M |
| P2-4 | [p2-4-inspections.md](p2-4-inspections.md) | 2.4 | 5.1–5.3 pre-trip checklist (web) | P2-3 | L |
| P2-5 | [p2-5-trip-events-fuel.md](p2-5-trip-events-fuel.md) | 2.5 | 7.1–7.6 events and fuel with documents (web) | P2-3 | L |
| P2-6 | [p2-6-dashboard-trip-zip.md](p2-6-dashboard-trip-zip.md) | 2.6, 2.7 | 9.2 dashboard counts; 2.5 ZIP per trip | P2-5 | M |
| P2-7 | [p2-7-acceptance.md](p2-7-acceptance.md) | — | Phase 2 acceptance, docs | all | S |

P2-1 → P2-3 is a chain. P2-4 and P2-5 both build on P2-3 and may run in either order; P2-6 needs both.

## Decisions every package follows (approved 23 Oct 2026)

- **Web, office staff only.** Phase 2 has no driver login: "Invite to app" (`drivers.user_id`) comes with the API in Phase 3 (3.1). Staff confirm equipment, record inspections and record events for the driver. The Driver role's `.own` permissions stay unused until Phase 3, but policies are written so a Driver-role user would only reach their own trips.
- **Free map.** Leaflet with OpenStreetMap tiles (attribution, no key) and a server-side `Geocoder` (P2-1). `shippers` and `trip_stops` store `place_source` + `place_ref`, not `google_place_id` (08 §9). Planned miles are typed in.
- **Trip numbers.** Per-company integer from 1 via `trip_number_sequences` under `SELECT … FOR UPDATE`; overridable with a unique number while the trip is a draft; locked on confirmation; the sequence never goes back.
- **Pre-trip gate.** A completed inspection is required to start a trip only when the company has `pre_trip_inspections` (the Company plan has `trips` without it, `PlanSeeder.php:42`).
- **Time zone.** `trip_date` defaults and the dashboard "today" use `companies.timezone` (Z1).
- **Assignment.** Terminated drivers and transferred-out (`transferred_out_at`) or deleted assets cannot be assigned. No co-driver.
- **New permission.** `inspections.manage` (write) in P2-4, added to the Dispatcher template.

## Already in place (verified 23 Oct 2026)

- [V] Features `trips`, `pre_trip_inspections`, `trip_events` (`app/Features/Feature.php:23-25`). Plans: Company has `trips` only; Enterprise has all (`database/seeders/PlanSeeder.php:42,53`).
- [V] Permissions `trips.view|create|edit|delete|assign|confirm-number`, `shippers.view|manage`, `trips.view.own`, `trips.drive.own`, `inspections.view`, `inspections.perform.own`, `trip-events.view|manage`, `trip-events.create.own`, `fuel.create.own` (`app/Authorization/PermissionCatalogue.php:83-100`). Dispatcher holds `trips.*`, `shippers.*`, `trip-events.*`, `inspections.view` (`app/Authorization/RoleTemplates.php:31-34`). There is no `inspections.manage`.
- [V] P1-4 hooks: `Folder::BINDINGS` maps `trip_id` → `trip` (`app/Models/Folder.php:28`); `folders.trip_id` and the `documents` subject columns `trip_id`, `trip_event_id`, `fuel_purchase_id`, `inspection_id` exist inside the CHECKs without FKs (`…_000400_create_folders_table.php:27`, `…_000500_create_documents_table.php:32-37`). `FolderService::ensureSystemFolder(Driver|Vehicle|Trailer)` (`app/Services/Documents/FolderService.php:23`), command `folders:ensure-system`, command `integrity:check`.
- [V] P1-6 hooks: `exports.type` includes `zip_trip` (`…_create_exports_table.php:19`); `ExportService::canAccess` / `canRequest` (`app/Services/Exports/ExportService.php:31,42`).
- [V] `asset_groups`: driver, vehicle, optional trailer, one active group per vehicle and per trailer; no co-driver. `fuel_types` is a platform lookup (`routes/platform.php:114`, `platform.settings.manage`).
- [V] `companies.timezone` (default `America/Chicago`); `App\Support\TimeZones`; `App\Support\NamedLock`; `App\Support\CheckConstraints`; `tests/Support/Perf.php` and `Actors.php`; `tests/Feature/Tenancy/IsolationMatrixTest.php`; `tests/Feature/Modules/AccessControl/RouteGuardMatrixTest.php`.
- [V] Vehicles and trailers have `transferred_out_at`; drivers can be terminated (`employment_status = terminated`).
- [V] `trip_number_sequences` exists since Phase 0 and `CompanyProvisioner` fills it (found in P2-2; the line below listed it as missing).
- [V] Missing: tables `shippers`, `trips`, `trip_stops`, `inspection_templates`, `inspection_template_items`, `inspections`, `inspection_results`, `trip_events`, `fuel_purchases`; any map library (only Alpine in `package.json`).
- [A] Until the first production deploy there is no real data, so create migrations may still be edited and environments rebuilt with `migrate:fresh --seed`.

## Rules for every session

- Read `CLAUDE.md` and load the skills the package names.
- **Plan first.** List every PENDING with a recommended default and wait for explicit approval. If the docs conflict with the code or with each other, stop and ask.
- **Schema.** New tables get their own create migration and follow [08](../08-database-schema.md) §1 and §9: cFKs, `UNIQUE(company_id, id)`, `CheckConstraints::add()` as the last statement, RESTRICT on columns used in a CHECK or a generated column. Never alter a table after its create (SQLite drops CHECKs). The FKs from `folders` and `documents` to the new tables go in MySQL-only migrations (the companies owner FK precedent, 08 §8).
- **Tenancy.** Tenant models use `BelongsToCompany`. Every route has auth, a permission and the feature middleware. Every id from a request is validated in the same company (`TenantRule`). Add every new tenant route with an id to the `IsolationMatrixTest` fixtures, and every new public route to `ACL_PUBLIC_ROUTES` in `RouteGuardMatrixTest` with its reason. Creating tenant rows in tests needs `TenantContext::run()`.
- **Permissions.** A new permission goes into `PermissionCatalogue` and its role templates, plus 06 §6–7; the deploy steps then list `PermissionSeeder` and `php artisan roles:sync-company`.
- **Files.** Always through `StoredFileService` and the P1-4 `DocumentService` on the private disk; downloads through an authorized controller; never a public URL.
- **Jobs.** `TenantAware` middleware, carry `companyId`, dispatch after commit, named queues per [12](../12-performance-plan.md) §3.
- **Audit.** Write the events [08](../08-database-schema.md) §10 requires: deletes, restores and voids, exports and ZIP downloads, DQ-document downloads. Trip number confirmation, assignment and status changes are audited too.
- **UI.** `x-ui` components (Basecoat), yajra tables for lists that grow, Lucide icons, `Toast`, `data-confirm` for destructive actions, theme tokens only (`ThemeTokensTest`). Rebuild and commit `public/build` when assets change.
- **SEO.** Every new app page is private (noindex); load `seo-checklist`.
- **Read-only access state.** Write permissions (`is_write`) are refused automatically; test it for each new write route.
- **Performance.** Query budget (`tests/Support/Perf.php`) for every new list and detail page; indexes from 08 §9 only.
- **Afterwards.** Record each decision taken in [14](../14-open-questions.md) §2 and the decision log in [../README.md](../README.md).

## AUTO-FINISH (every package ends with this)

```
AUTO-FINISH (do all of this yourself after I approve the plan; do not ask me to run commands):
0. Start: if on main, create branch p2/<package name>; if the working tree is dirty, stop and tell me.
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

## Deploy checklist for Phase 2

Filled in by each package's final report and confirmed against the code in P2-7. Known so far:
- `php artisan migrate` (the Phase 2 tables, then the MySQL-only FK migrations on `folders` and `documents`).
- `PermissionSeeder`, then `php artisan roles:sync-company` (P2-4 adds `inspections.manage`).
- The default pre-trip inspection template seeder (P2-4).
- `php artisan folders:ensure-system` covers trips (P2-2); idempotent. P2-2 adds no permission, env key, queue or schedule.
- P2-3 (assignment and lifecycle) adds no migration, permission, env key, queue or schedule; nothing to run beyond the code.
- `npm run build` output committed (Leaflet, P2-1). [L] OpenStreetMap attribution on every map; a tile or geocoding provider once traffic outgrows the public policies.
- Env (P2-1, all optional): `MAPS_GEOCODER` (`photon` default, `nominatim`, or `none` to switch search off), `MAPS_GEOCODER_URL`, `MAPS_GEOCODER_CONTACT` (defaults to `MAIL_FROM_ADDRESS`, sent in the User-Agent), `MAPS_GEOCODER_TIMEOUT` (4 s), `MAPS_GEOCODER_CACHE_DAYS` (30), `MAPS_TILE_URL`, `MAPS_TILE_ATTRIBUTION`. The app-wide geocoder limit uses the cache store, so production needs a shared cache (`database` today).

## Deploy checklist for Phase 1 (keep until the first production deploy)

Confirmed against the code in P1-7 (23 Oct 2026). [V] unless marked.

- `composer install` (adds `laravel/socialite`), `php artisan migrate` (`social_accounts`, `site_settings` provider switches, `folders`, `documents`, `exports`).
- `php artisan folders:ensure-system` once, for drivers, vehicles and trailers that already exist (P1-4). It is idempotent.
- Queue workers: one for the `default` queue (P1-5 `ConvertImageToPdf`, job timeout 60 s under the 90 s `retry_after`; PHP `memory_limit` at least 512M), and one on the `exports` connection: `php artisan queue:work exports --queue=exports --timeout=900 --memory=512` (P1-6, `retry_after` 960 s).
- Scheduler every minute; it runs `exports:prune` daily (`routes/console.php`).
- Env: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`, `FACEBOOK_REDIRECT_URI` (register `{APP_URL}/auth/{google|facebook}/callback` with the providers, P1-3); optional `DOCUMENTS_CONVERT_IMAGES` (default on, P1-5), `EXPORTS_QUEUE_DRIVER` (default `database`) and `EXPORTS_MAX_BYTES` (default 2 GB, P1-6).
- Platform > Settings > Site: turn on Google and Facebook sign-in once the keys are set (both off by default).
- PHP: gd with WebP support, exif (P1-5), zip (P1-6). Mail must work for the export ready/failed notices. [A] The local disk needs free space for a ZIP of up to 2 GB of source files while it is built.
- [L] Google OAuth consent screen and Facebook app review, with a data-deletion instructions page (P1-3), before the switches are turned on.

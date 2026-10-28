# 12 · Performance plan

This plan is prioritised by evidence. **P1** items fix verified problems or protect the multi-tenant design. **P2** items come with the new modules. **P3** items wait until measurements say they are needed.

## 1. Expected access patterns [A]

| Pattern | Volume driver |
|---|---|
| List screens (drivers, vehicles, documents, trips) | Per company, tens to a few thousand rows. Always filtered by `company_id`, then status or date. |
| Compliance dashboards | Per company, every driver or asset × document type, 25 per page [V] |
| Location pings | Per active truck, one batch every 15–60 s while driving. The largest table by far. |
| Messages, trip events, fuel | Moderate, per trip |
| Reports and exports | Per company, date-ranged, run occasionally as queued jobs |
| Permission and entitlement checks | Every request, often several times per request |

## 2. P1: fix now (Phase 0)

| # | Item | Evidence | Action |
|---|---|---|---|
| 1 | N+1 on service-log categories | `ServiceLogController.php:43` (relation read at `:101`, not eager-loaded) | `->with('maintenanceCategories')`, and `withCount('documents')` instead of loading documents only to count them (`:79`) |
| 2 | Drivers list loads every licence and sorts in PHP | `DriverController.php:35-37,82` | `currentLicense()` relation on `licenses.is_current` (unique), eager-loaded |
| 3 | Compliance subqueries lack composite indexes | `DriverComplianceService.php:137-204`, `FleetComplianceService.php:34-113`; migrations have only the FK indexes | Indexes `(owner_id, document_type_id, expiry_date)` and `(company_id, expiry_date)` on the three compliance tables |
| 4 | "Upload to all" runs a select plus an insert per asset, and re-finds each driver | `DriverDocumentUploadController.php:237`; `DocumentUploadController.php:210,306` | One scoped query for the target ids, then a chunked bulk insert inside a transaction. Files are stored once and referenced by `file_id`. |
| 5 | Admin subscription dashboard loads every subscription | `SubscriptionAdminController.php:29` | SQL `COUNT` / `SUM` over the new company-keyed tables |
| 6 | Tenant-leading indexes | List queries filter by company first | Every tenant table: an index starting with `company_id`, matching its list filter and sort ([08](08-database-schema.md)). Redundant single-column indexes are dropped ([09](09-erd-audit.md) F22). |
| 7 | Permission checks | **Measured:** stock Spatie teams at 2,000 companies needs 150+ ms per request to load a 13 MB cache, and 86–90 s plus 3.6 GB to rebuild it after any role edit ([06](06-roles-permissions.md) §2.2) | **Decided:** database-first resolver (one indexed query per request, memoised; measured p95 0.29 ms at 2,000 companies, 0.35 ms at 5,000, 15k resolutions/s with 8 workers). The Spatie registrar caches the permission catalogue only. |
| 8 | Entitlement and access-state checks on every request | New design | Resolve once per request (memoised in `TenantContext`). Cached under a versioned key. No database query after the first request until the version changes. |
| 9 | Webhooks run synchronously | `StripeWebhookController.php` | Inbox table plus a queued job on the `billing` queue ([07](07-subscription-billing.md) §6.2) |

## 3. P2: build in with the new modules

| Area | Design |
|---|---|
| Pagination | Server-side for every list (yajra or `paginate`). Cursor pagination for message history and API lists of trips and events (stable under inserts). |
| Search | Today it uses leading-wildcard `LIKE` across many columns plus `orWhereHas(company)` (`DriverController.php:159-170` [V]). Under tenancy, drop the company join (always one company). Keep `LIKE` within a company (small sets). Add a MySQL FULLTEXT index on driver names only if measured slow. |
| Location pings | Received in batches through the API → queued insert using `insert()` of up to 500 rows. No FKs (by design). Index `(trip_id, recorded_at)`. The live map reads `vehicle_last_locations` (one row per vehicle), never the ping table. Miles are kept as a running total in `trips.actual_distance_miles`. |
| Ping retention | `location-pings:prune` deletes in chunks of 10k by `recorded_at` (13-month default until counsel confirms [L]). When the table passes roughly 50M rows, switch to monthly RANGE partitions on `recorded_at`. That needs `recorded_at` in the primary key, so plan it as a P3 migration. |
| Exports and ZIP | Queued, one company per job. Files are streamed into the ZIP (`ZipArchive::addFile` from a local stream or a temporary copy for S3), so memory stays flat. Size cap and a time limit per job. Chunked `cursor()` reads for the year-end report. Built (P1-6): `BuildExport` runs on its own `exports` queue connection (`retry_after` 960 s > job timeout 900 s), one build at a time per company through `WithoutOverlapping` (a unique lock would drop a second scope's job); already-compressed files are stored uncompressed. Measured with `ExportScaleTest` (200 drivers plus 200 trips since P2-6). |
| Reports (profit and loss, year-end) | Aggregates in SQL by company and date range, using `(company_id, completed_at)` on trips, `(company_id, purchased_at)` on fuel, `(company_id, service_date)` on service logs and `(company_id, incurred_on)` on expenses. A daily rollup table (`company_daily_financials`) is added **only** if the report is measured slow. |
| Transactions and locking | Short transactions only. Every lock goes through the company row first, then children, so locks are always taken in the same order (no deadlocks): limit checks, trip numbers (`trip_number_sequences` row) and subscription updates in the webhook job. |
| Queues | Separate queues: `billing`, `notifications`, `exports`, `tracking`, `integrations`, `default`. Tenant jobs carry `companyId`. Export and sync jobs run one at a time per company (`WithoutOverlapping` for exports, P1-6). |
| Caching | Tenant keys follow `tenant:{id}:{area}:{version}`. Cached: entitlements, access state, the company settings row. Dashboard counts are live: one conditional-aggregate query per table on tenant-leading indexes (trip counts on `trips(company_id, status)`, P2-6); cache them (60 s) only if measured slow. Not cached: anything a user can change and immediately expect to see (lists). |
| Files at scale | The disk is set by config. **S3 (or compatible) recommended for production [R]**, with private objects, server-side encryption and versioning. Downloads go through an authorized controller that returns a short-lived temporary URL. Paths follow `companies/{id}/{area}/{yyyy}/{mm}/{hash}` so a company's files can be listed and purged by prefix. |
| Image to PDF | Done in a queued job after upload. dompdf is already installed [V]. Image size is capped before conversion. |
| Eager loading | `Model::preventLazyLoading()` in non-production environments, so N+1 bugs fail the tests |

## 4. P3: only when measurements show a need

- Read replica for reports.
- Partitioning of `location_pings` and `audit_logs`.
- A per-user permission cache. Not needed at the measured 0.2–0.5 ms per request; add it only if `PermissionScaleTest` regresses.
- A search engine (Meilisearch or similar) for documents.

## 5. Measuring

| Metric | Target [A] | How |
|---|---|---|
| p95 page response, tenant list screens | < 300 ms | APM or Laravel Pail logs plus a slow-query log |
| Queries per list request | Constant; no growth with rows | `tests/Support/Perf.php` query-budget tests (already used [V]) extended to the new modules |
| Permission resolution p95 | < 5 ms (measured 0.29 ms at 2,000 companies, 0.35 ms at 5,000) | `Authorization/PermissionScaleTest` on MySQL CI |
| Ping ingestion | 1,000 pings/s sustained on staging | Load test before the Phase 3 launch |
| Export of a 200-driver company | < 5 min, flat memory | Phase 1 test |

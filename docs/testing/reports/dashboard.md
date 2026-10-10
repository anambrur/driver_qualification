# Module report: `dashboard`

Run date: 2026-10-10. Scope: `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php` (`admin/profit.blade.php` was already deleted by ACL-04).

Baseline: `php artisan test` → 1070 passed, 0 failed. After: 1077 passed, 0 failed (7 new tests, no regressions).

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| DSH-01 | P2 | FIXED (earlier, by ACL-04) | `tests/Feature/Modules/Dashboard/DashboardTest.php::DSH-01 → /profit stays removed → it returns 404 for guests and tenants` | none in this run (route, `profit()` and view were removed in the access-control run) |
| DSH-02 | P2 | FIXED | `DashboardTest.php::DSH-02 → dashboard query budget → it computes the stats with a small, fixed number of queries`; `…::it does not grow with the amount of data`; `…::it still reports correct numbers for the tenant and ignores other companies`; `…::it shows company-wide stats to the super-admin` | `app/Http/Controllers/DashboardController.php` |
| DSH-03 | P3 | FIXED | `DashboardTest.php::DSH-03 → draft drivers → it matches the tenant driver list, which hides drafts`; `…::it still counts drafts for the super-admin, like the driver list does` | `app/Http/Controllers/DashboardController.php` |

## Details

- **DSH-02.** The query count was already constant (it did not grow with data), but the stats took 15 separate `COUNT` queries for a tenant (17 for a super-admin), 25 per request in total. They are now conditional aggregates (`SUM(CASE WHEN …)`), one query per table: drivers, vehicles, trailers, the 3 document tables, service logs, plus companies for the super-admin. That's 7 stats queries (8 for super-admin) and about 17 per request. The budget test allows 18. The numbers are unchanged, and a test checks every stat against seeded data with a second company present.
  - **The `expiry_date` index was not added.** With the aggregate form, each document table is read once, filtered by the tenant `EXISTS` on the FK-indexed `*_id` column, and every expiry bucket comes out of the same pass. An `expiry_date` index would not be used by this query, so no migration was added.
- **DSH-03.** For non-super-admins the dashboard now excludes `status = 'draft'`, the same rule `DriverController::index` uses for the list and its status counts. Super-admins still see drafts in both places. Draft public applications therefore no longer count toward "new applicants (7 days)" for tenants either. The driver document overdue/due-soon counts still include any documents on draft drivers, as before. I didn't change these because drafts normally have no compliance documents.

## Out-of-scope changes

None.

## Larastan

`vendor/bin/phpstan analyse app/Http/Controllers/DashboardController.php` reports 3 errors (`larastan.relationExistence` for `driver`/`vehicle`/`trailer` in the `whereHas` calls). They were there before this run and are on unchanged lines. The cause is the relation methods in `DriverComplianceDocument`, `VehicleDocument` and `TrailerDocument`, which have no `BelongsTo` return types. Those models belong to the driver-compliance / fleet-compliance modules, so they were left alone. No new errors.

## Deferred items

None. (The related question of whether `/dashboard` should need `Subscribed` is SUB-04 in `subscription-lifecycle`, which is already deferred.)

## Manual steps

None (no migrations, no config changes).

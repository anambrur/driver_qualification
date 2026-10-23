# P2-6: Dashboard trip counts and the ZIP per trip (tasks 2.6 and 2.7)

```
Read docs/architecture/agent-prompts/README.md (rules, decisions and AUTO-FINISH), CLAUDE.md,
08-database-schema.md §9 (trips indexes, the "pending pickup" definition on trip_stops) and §12 (exports),
02-requirement-matrix.md rows 9.2 and 2.5, 10-workflows.md §9, 12-performance-plan.md §3 and §5,
14-open-questions.md §2 P1-6 and §3 "Before Phase 2" (ZIP per trip on P1-6).
Skills: multi-tenant-safety, db-performance, queue-and-jobs, security-checklist, laravel-conventions,
seo-checklist, pest-testing.
Task: Phase 2 tasks 2.6 and 2.7. Package name: p2-6-dashboard-trip-zip. Requires P2-4 and P2-5 on main.

Build:
1. Dashboard (9.2): "On the road" = in_progress trips; "Pending pickup" = assigned or in_progress trips whose pickup
   stop (or start stop with also_pickup) has no departed_at (08 §9). One aggregate query on the
   (company_id, status) index; each card links to the filtered trips list. Shown with @feature('trips') and
   trips.view only.
2. ZIP per trip on P1-6 (14 §3): ExportService::canAccess / canRequest for zip_trip (exports.create, trips.view, and
   folders.view when the company has folders); the trip_id scope parameter; an ExportContents trip section (trip
   folder documents with their original images, event, fuel and inspection attachments) laid out as
   `Trips/<number> (#id)/…` with the P1-6 naming rules; the route and a button on the trip page; an
   IsolationMatrixTest fixture. Audit as P1-6 does.
3. Tests: counts match the trip states (acceptance), including also_pickup and voided or cancelled trips; query
   budget for the dashboard; the trip ZIP holds what the trip page shows and nothing from another trip or company;
   permission and feature refusals; read-only company can still export (Q2); extend ExportScaleTest (group scale)
   with trips.

PENDING, recommended defaults:
- Trips in the company ZIP: recommend yes, a `Trips/` section (Q8: everything attached to the entity), so the
  scale test covers 200 drivers plus their trips.
- A generated trip summary file in the ZIP: recommend no (documents only); a summary belongs to the Phase 4 reports.
- Cancelled and soft-deleted trips: recommend cancelled included, soft-deleted excluded (as P1-6 does for drivers).
- Dashboard for a company without trips: recommend the cards hidden, no upgrade prompt.

Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

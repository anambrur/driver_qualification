# P2-2: Trips, trip numbers, stops and the trip folder (task 2.2)

```
Read docs/architecture/agent-prompts/README.md (rules, decisions and AUTO-FINISH), CLAUDE.md,
08-database-schema.md §1, §2, §8 (folders and documents, the trip binding) and §9 (trip_number_sequences, trips,
trip_stops), 10-workflows.md §13, 02-requirement-matrix.md rows 4.1–4.3, 6.1 and 6.3, 14-open-questions.md §2
"Phase 2 plan" and §3 "Before Phase 2" (trip folders on P1-4), 03-multi-tenant-architecture.md,
12-performance-plan.md §3.
Skills: multi-tenant-safety, repository-service-pattern, db-performance, security-checklist,
laravel-conventions, seo-checklist, pest-testing.
Task: Phase 2 task 2.2. Package name: p2-2-trips. Requires P2-1 on main.
This is a large package: plan it as three commits on one branch (schema and services; UI; trip folder, integrity
and tests).

Build:
1. Migrations for trip_number_sequences, trips and trip_stops exactly as 08 §9: cFKs (driver, vehicle, trailer,
   shipper RESTRICT; user pointers SET NULL), the generated active_driver_id / active_vehicle_id with their unique
   indexes (CASE WHEN, works on both engines), the status CHECK through CheckConstraints::add(), every listed
   index including (company_id, completed_at), soft deletes on trips. The assignment columns exist now; the
   assignment UI is P2-3.
2. Models Trip, TripStop, TripNumberSequence (BelongsToCompany), factories with a state per status.
3. TripService:
   - Create a draft: the number comes from trip_number_sequences with SELECT … FOR UPDATE in the same transaction
     as the insert; trip_date defaults to today in companies.timezone; created_by_user_id.
   - Edit details: product, weight, weight_unit, shipper (active, same company), notes, planned_distance_miles
     (typed in). Override the number with a unique number while draft; the sequence never goes back.
   - Confirm number and date (trips.confirm-number): number_confirmed_at / _by; the number is then locked.
   - Stops: one start (with also_pickup), an optional pickup, one drop-off; address, lat/lng and place columns
     from the P2-1 picker, optional shipper; sequence kept in order.
   - draft → planned, cancel (draft, planned, assigned; with a reason), soft delete, as the PENDING below decide.
4. Trip folder (14 §3): a MySQL-only migration adding the cFKs folders.trip_id and documents.trip_id → trips
   (RESTRICT). FolderService::ensureSystemFolder accepts a Trip; the system folder is created in the trip's create
   transaction; folders:ensure-system covers trips; integrity query 5 (subject vs folder) covers trips. The trip
   page gets the P1-4 folder browser as its Documents tab.
5. UI: Trips in the Operations group. Yajra list (number, date, status badge, shipper, start → drop-off; driver and
   vehicle columns ready for P2-3) with status and date filters. Create page; trip page with Details, Stops (Leaflet
   map with the stop markers) and Documents tabs; confirm-number and cancel actions with data-confirm where
   destructive. Feature trips; permissions trips.view, trips.create, trips.edit, trips.delete,
   trips.confirm-number.
6. Audit: trip.created, trip.number_confirmed, trip.cancelled, trip.deleted.
7. Tests: numbers are sequential and unique per company, and two companies don't share a sequence; a
   --group=mysql concurrency test (parallel creates get distinct numbers, the Limits ConcurrencyTest pattern);
   override while draft only and unique; confirmation locks the number; every refused status transition; the status
   CHECK at DB level (QueryFailure helper); stop rules; trip_date in a non-Chicago time zone near midnight UTC;
   trip folder created, folders:ensure-system idempotent with trips, integrity query rows; isolation for every new
   route (IsolationMatrixTest fixtures); read-only company; feature off; query budget for the list and the trip page.

PENDING, recommended defaults:
- Sequence row: recommend created lazily on the first trip (insertOrIgnore, then lock), so existing companies need
  no backfill.
- draft → planned: recommend automatic once the number is confirmed and a start and a drop-off exist; the trip
  stays planned while stops are edited.
- Stops: recommend exactly one start, at most one pickup and exactly one drop-off (one load per trip, Q6).
- Delete: recommend only draft or cancelled trips, soft deleted; the number is never reused.
- Load fields: recommend weight_unit lb (kg allowed); load_pay and currency hidden until Phase 4 (driver_pay).
- Trip folder name: recommend stored as `Trip #<id>` (reserved like the other system folders) and shown as
  "Trip documents".
- Shipper on a used trip: recommend shippers stay soft-deletable; a trip keeps showing a deleted shipper's name.

Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

# P2-3: Assignment, equipment confirmation and the trip lifecycle (task 2.3)

```
Read docs/architecture/agent-prompts/README.md (rules, decisions and AUTO-FINISH), CLAUDE.md,
08-database-schema.md §7 (drivers' status, vehicles, trailers, asset_groups) and §9 (trips, trip_stops),
10-workflows.md §6 (Terminate) and §13 steps 2–5, 02-requirement-matrix.md rows 4.5, 4.6 and 9.2 (pending pickup),
06-roles-permissions.md §6–7 (trips.assign, own-record policies), 14-open-questions.md §2 "Phase 2 plan".
Skills: multi-tenant-safety, repository-service-pattern, db-performance, security-checklist,
laravel-conventions, seo-checklist, pest-testing.
Task: Phase 2 task 2.3. Package name: p2-3-assignment-lifecycle. Requires P2-2 on main.

Build:
1. Assignment (trips.assign): driver, vehicle and optional trailer on a planned or assigned trip → assigned;
   unassign → planned. The form defaults to the driver's active asset group. Every id in the same company
   (TenantRule). Refused: a terminated or not-hired driver, a deleted or transferred-out (transferred_out_at)
   vehicle or trailer.
2. Equipment confirmation (4.6): staff with trips.assign confirm the tractor and trailer for the driver
   (equipment_confirmed_at / _by). Changing the vehicle or trailer clears it.
3. Lifecycle in a TripLifecycleService:
   - Start: assigned → in_progress; started_at, vehicle_unit_no_snapshot and trailer_unit_no_snapshot. The unique
     active_driver_id / active_vehicle_id is the guard: a unique violation becomes a clear 422 ("this driver already
     has a trip in progress"). Leave one clearly named hook where P2-4 adds the pre-trip gate.
   - Picked up: sets the pickup stop's (or the start stop's, with also_pickup) departed_at; arrived_at / departed_at
     editable on the other stops.
   - Complete: in_progress → completed; completed_at; actual_distance_miles typed in (no GPS until Phase 3).
   - Cancel from assigned clears the active guards; in_progress cannot be cancelled (10 §13).
4. TripPolicy: trips.view / trips.edit / trips.assign for staff, and the own-record rule for trips.view.own (only a
   trip whose driver is linked to the user) ready for Phase 3; no driver-facing route yet.
5. UI on the trip page: Assignment card (driver, vehicle, trailer pickers with search, asset-group defaults),
   Confirm equipment, Start, Picked up, Complete and Cancel actions shown by status and permission; driver and
   vehicle columns on the trips list.
6. Audit: trip.assigned, trip.unassigned, trip.equipment_confirmed, trip.started, trip.picked_up, trip.completed.
7. Tests: a driver cannot have two in-progress trips and a vehicle cannot be on two (acceptance; service and DB
   level, on MySQL too); the status CHECK; asset-group defaults; terminated driver and transferred-out asset refused;
   another company's ids refused; equipment cleared on change; snapshots taken at start; a state-transition table
   test; isolation for every new route; read-only company; query budget for the trip page.

PENDING, recommended defaults:
- Equipment confirmed before start: recommend required.
- Another assigned (not started) trip for the same driver or vehicle: recommend a warning only; the hard rule is the
  one in-progress guard.
- Expired CDL or medical card on the driver: recommend a visible warning on assignment, not a block (the carrier
  decides; [L] 391.41 / 391.51 make driving without them the carrier's violation).
- Permissions: recommend trips.assign for assign, unassign and equipment; trips.edit for start, picked up,
  complete and cancel.
- actual_distance_miles on complete: recommend optional, prefilled with planned_distance_miles.
- Trips on the driver and vehicle pages: recommend a small recent-trips table on each (x-ui.table, 10 rows, link to
  the filtered list).

Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

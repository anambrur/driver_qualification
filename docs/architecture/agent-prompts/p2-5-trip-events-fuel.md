# P2-5: Trip events and fuel purchases with documents, web (task 2.5)

```
Read docs/architecture/agent-prompts/README.md (rules, decisions and AUTO-FINISH), CLAUDE.md,
08-database-schema.md §1, §2, §8 (documents subjects, the subject vs folder rule) and §9 (trip_events,
fuel_purchases), 10-workflows.md §13 step 4, 02-requirement-matrix.md rows 7.1–7.6, 14-open-questions.md §2 C9 and
"Phase 2 plan".
Skills: multi-tenant-safety, db-performance, security-checklist, laravel-conventions, seo-checklist, pest-testing,
queue-and-jobs (photo conversion of attachments).
Task: Phase 2 task 2.5. Package name: p2-5-trip-events-fuel. Requires P2-3 on main.
This is a large package: plan it as two commits on one branch (schema and services; UI and tests).

Build:
1. Migrations exactly as 08 §9: trip_events with all five CHECKs through CheckConstraints::add() and the accident
   register columns, fuel_purchases (unique trip_event_id, UNIQUE(company_id, id)); cFKs RESTRICT, user pointers SET
   NULL, the listed indexes. A MySQL-only migration adding the cFKs documents.trip_event_id and
   documents.fuel_purchase_id (RESTRICT).
2. TripEventService (trip-events.manage): record stop, police_stop, weigh_station, accident, breakdown on a trip;
   driver and vehicle copied from the trip. Service rules from 08 §9: a ticket file when ticket_issued = 1;
   documents for a weigh station with weight_ok = 0 and for an accident (licence, insurance, photos). Evidence is
   never deleted: void with a reason (voided_at, void_reason).
3. Fuel stop (7.6): a fuel trip_event and its fuel_purchases row in one transaction; location, state, gallons,
   price per gallon, total, fuel type (platform lookup), odometer. "Done" = confirmed_at, set once the receipt is
   uploaded.
4. Attachments go into the trip folder with subject trip_event_id or fuel_purchase_id through DocumentService
   (photos become PDFs as in P1-5); integrity query 5 covers them.
5. UI: an Events tab on the trip page: a timeline (newest first, voided ones struck through), buttons Add stop,
   Police stop, Weigh station, Accident, Breakdown, Fuel stop opening dialogs with the conditional fields and
   uploads; void with data-confirm and a reason; the fuel "Done" state. Feature trip_events on every route and menu
   entry.
6. Audit: trip_event.recorded, trip_event.voided, fuel_purchase.confirmed.
7. Tests: every CHECK on both engines (QueryFailure helper), each service rule, void instead of delete, the fuel
   event and purchase are written together or not at all, unique trip_event_id, attachments in the trip folder,
   isolation for every new route (IsolationMatrixTest fixtures), read-only company, feature trip_events off (the
   Company plan) hides the tab and refuses the routes, query budget for the trip page with 50 events.

PENDING, recommended defaults:
- When events can be recorded: recommend in_progress trips, plus completed trips for 7 days (late paperwork);
  never on draft, planned or cancelled trips.
- Event location: recommend the P2-1 address picker, optional; occurred_at required, defaulting to now in the
  company time zone.
- Required accident and weigh documents: recommend at least one attachment, with the document type chosen from a
  new set of document types for the trip module (seeded: Police ticket, Weight ticket, Driver licence, Insurance
  card, Accident photo, Fuel receipt).
- Fuel total: recommend typed in and checked against gallons × price within 1%, with a warning, not a block.
- Currency: recommend USD only for now.
- Voiding a fuel event: recommend it voids the purchase too (excluded from later Phase 4 totals); voiding needs
  trip-events.manage.

Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

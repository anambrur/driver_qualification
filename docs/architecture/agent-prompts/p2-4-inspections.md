# P2-4: Pre-trip inspection templates and inspections, web (task 2.4)

```
Read docs/architecture/agent-prompts/README.md (rules, decisions and AUTO-FINISH), CLAUDE.md,
08-database-schema.md §1, §8 (documents.inspection_id) and §9 (inspection_templates, inspection_template_items,
inspections, inspection_results), 02-requirement-matrix.md rows 5.1–5.3, 14-open-questions.md §2 C10 and
"Phase 2 plan", 04-platform-administration.md (lookups, platform.settings.manage), 06-roles-permissions.md §6–7.
Skills: multi-tenant-safety, db-performance, security-checklist, laravel-conventions, seo-checklist, pest-testing.
Task: Phase 2 task 2.4. Package name: p2-4-inspections. Requires P2-3 on main.
This is a large package: plan it as two commits on one branch (templates and platform screens; inspections, gate
and tests).

Build:
1. Migrations exactly as 08 §9: inspection_templates and inspection_template_items (Reference, platform-managed),
   inspections (tenant, cFKs RESTRICT) and inspection_results (unique (inspection_id, template_item_id), item_label
   snapshot). A MySQL-only migration adding the cFK documents.inspection_id → inspections (RESTRICT).
2. Seeder: one published "Pre-trip" template, version 1, with sections and required items (a pre-trip checklist,
   not a DVIR, C10). Added to DatabaseSeeder and safe to re-run.
3. Platform screen under the lookups (platform.settings.manage): list, draft, edit items while the version has
   never been used, publish, "new version" (copies the items), deactivate. A used version is never edited.
4. Permission inspections.manage (write): PermissionCatalogue, the Dispatcher template, 06 §6–7.
5. Inspections on a trip (inspections.manage to record, inspections.view to read): start from an assigned trip
   (driver, vehicle and trailer copied from the trip; template = the active published version); one screen grouped
   by section with pass / fail / n/a and a note per item; saved as you go; progress = answered ÷ items (5.3);
   complete once every required item is answered (result = defects_found if any fail, else no_defects); odometer;
   abandon. Photos or files for failed items go into the trip folder with subject inspection_id through
   DocumentService (integrity query 5 covers them).
6. The pre-trip gate in the P2-3 hook: when the company has pre_trip_inspections, starting a trip needs a completed
   inspection of that trip; without the feature, no gate.
7. UI: an Inspection tab on the trip page (status, progress bar, results, attachments); an inspection detail page.
   Feature pre_trip_inspections on every route and menu entry.
8. Audit: inspection.completed, inspection.abandoned; platform audit for template publish and deactivate.
9. Tests: progress; required items; result; a used version can't be edited and a new version copies items; result
   rows keep the label snapshot; gate on with the feature and off without it (the Company plan); attachments in the
   trip folder; isolation for every new route; read-only company; platform permission; query budget for the form.

PENDING, recommended defaults:
- Defects found: recommend the trip cannot start until a later inspection of it has no defects (396.13: the
  driver must be satisfied the vehicle is safe); the dispatcher sees the failed items.
- Active templates: recommend one active published pre_trip template at a time; new inspections take it, old ones
  keep their version.
- Inspections without a trip (trip_id is nullable): recommend not in this package; every web inspection belongs to
  a trip.
- A company-wide inspections list: recommend not yet (trip tab only); add one when the client asks.
- Default checklist items: recommend the usual pre-trip sections (engine compartment, cab and controls, lights,
  brakes and air system, tires and wheels, coupling, trailer, emergency equipment); the client can review them in
  the platform screen.

Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

# P2-7: Phase 2 acceptance and docs

```
Read docs/architecture/agent-prompts/README.md (rules and AUTO-FINISH), CLAUDE.md,
13-implementation-roadmap.md (Phase 2 acceptance), 02-requirement-matrix.md rows 2.5, 4.1–4.6, 5.1–5.3, 6.1, 6.3,
7.1–7.6, 9.2, docs/DriverFilesHub-Features.md, docs/Technical-Features-and-Workflow.md, docs/erd.md.
Skills: quality-gate, pest-testing.
Task: close Phase 2. Package name: p2-7-acceptance. Requires P2-1 to P2-6 on main.

1. Check each Phase 2 acceptance criterion in 13 and report pass or fail with evidence (test names, timings):
   the trip lifecycle (10 §13) end-to-end on the web; a driver cannot have two in-progress trips; the event CHECK
   rules; dashboard counts match the trip states. Add one end-to-end feature test that walks a trip from draft to
   completed (shipper, stops, confirmation, assignment, equipment, inspection, start, events and fuel with
   documents, pickup, completion, trip ZIP) if no existing test already covers it.
2. On a fresh MySQL scratch database: migrate:fresh --seed, folders:ensure-system, integrity:check (no rows),
   the full suite, --group=mysql and --group=scale.
3. Docs, status changes only (no rewrites): 02 statuses for the rows above; 13 marks Phase 2 done with the date;
   the status icons in DriverFilesHub-Features.md and Technical-Features-and-Workflow.md; every decision taken in
   P2-1 to P2-6 in 14 §2 and in the README decision log; docs/erd.md gets the Phase 2 tables (re-read from the MySQL
   schema).
4. Confirm the Phase 2 deploy checklist in agent-prompts/README.md against what was built (migrations, seeders,
   PermissionSeeder + roles:sync-company, commands, env keys, assets, [L] map attribution and policies).
5. List what Phase 3 needs before it starts: driver invitation and login (3.1), the `.own` policies already in
   place, location-tracking notice and consent [L], push providers, and anything P2-1 to P2-6 left PENDING.
   Do not write the Phase 3 prompts.

Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

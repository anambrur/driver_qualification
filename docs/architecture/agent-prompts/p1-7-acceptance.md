# P1-7: Phase 1 acceptance and docs

```
Read docs/architecture/agent-prompts/README.md (rules and AUTO-FINISH), CLAUDE.md,
13-implementation-roadmap.md (Phase 1 acceptance), 02-requirement-matrix.md rows 1.4, 1.8, 2.1–2.5, 3.5,
docs/DriverFilesHub-Features.md, docs/Technical-Features-and-Workflow.md. Skills: quality-gate, pest-testing.
Task: close Phase 1. Package name: p1-7-acceptance. Requires P1-1 to P1-6 on main.

1. Check each Phase 1 acceptance criterion in 13 and report pass or fail with evidence (test names, timings):
   nested custom folders; JPG → PDF with the original kept; 200-driver company ZIP under 5 minutes with flat
   memory (perf group on MySQL); plates on asset lists and details. Add smoke checks for social login and the
   grants screen.
2. On a fresh MySQL scratch database: migrate:fresh --seed, folders:ensure-system, integrity:check (no rows),
   the full suite, --group=mysql and the perf group.
3. Docs, status changes only (no rewrites): 02 statuses for the rows above; 13 marks Phase 1 done with the date;
   the status icons in DriverFilesHub-Features.md and Technical-Features-and-Workflow.md; every decision taken in
   P1-1 to P1-6 in 14 and in the README decision log; docs/erd.md gets social_accounts, folders, documents, exports.
4. Confirm the deploy checklist in agent-prompts/README.md against what was built.
5. List what Phase 2 needs before it starts: still-open questions (co-driver, C7, Maps API terms [L]),
   how trip folders plug into P1-4, and ZIP per trip on top of P1-6. Do not write the Phase 2 prompts.

Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

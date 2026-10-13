# WP1: Schema rebuild (tasks 0.1 rest, 0.2, 0.13)

```
Read CLAUDE.md, docs/architecture/README.md, 08-database-schema.md, 09-erd-audit.md,
11-migration-strategy.md (§2-4, §6), docs/erd-proposed.md, and 13-implementation-roadmap.md (Phase 0).
Task: Phase 0 tasks 0.2 and 0.13, plus the remaining 0.1 spike items
(CHECK/generated-column behaviour on SQLite, companies owner FK on SQLite).

Rebuild the schema by editing the create-table migrations in the group order of 11 §3
(groups A-J and O, plus stored_files). Apply ERD fixes F1-F29 from 09. Add medical
certificate and CDL expiry to the compliance engine (F17).
Also: update factories, add tests/Support/Actors.php per 11 §5, add the integrity:check
command with the 8 queries of 11 §6, and a seed smoke test.
Do NOT touch controllers or routes. List the tests that break and why.

Plan mode first. Split into 3 reviewable steps (reference+platform tables,
tenancy+auth tables, drivers/fleet/billing/files/audit). Label claims [V]/[R]/[A],
mark unknowns PENDING, and wait for my approval after each step.

Acceptance: migrate:fresh --seed works on SQLite and MySQL 8.0.16+; integrity queries return no rows.
```

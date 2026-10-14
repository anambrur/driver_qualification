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

AUTO-FINISH (do all of this yourself after I approve the plan; do not ask me to run commands):
0. Start: if on main, create branch p0/<this-wp-name>; if the working tree is dirty, stop and tell me.
1. Implement the approved plan. If any view, layout or route changed, load skill seo-checklist (private pages = noindex).
2. Gates, on changed files only, fixing and re-running until green:
   a. vendor/bin/pint --dirty
   b. vendor/bin/phpstan analyse --memory-limit=1G <changed paths>
   c. php artisan test --filter=<area of this WP>
   d. composer test (full suite). Exception: WP1 is expected to break old tests; there, run the new/migration/integrity tests and list the broken ones instead.
3. Run the code-review skill at high effort on the diff. Fix CONFIRMED findings, re-run step 2. List PLAUSIBLE ones for me.
4. Commit with a clear message (end with the Co-Authored-By line from the session's attribution reminder). Never push.
5. Merge into main locally (git checkout main && git merge --no-ff <branch>) ONLY if every gate in steps 2-3 passed. Otherwise stay on the branch and report what failed, with the test output.
6. Final report: gates passed/failed with evidence, remaining PENDING items, the next WP.
Never use --no-verify, skip tests, or weaken assertions to get green.
```

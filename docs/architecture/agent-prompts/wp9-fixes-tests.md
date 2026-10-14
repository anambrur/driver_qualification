# WP9: Bug fixes, performance, test suite, CI (tasks 0.12, 0.14)

```
Read CLAUDE.md, 01-current-architecture-assessment.md (§5), 12-performance-plan.md (§2),
11-migration-strategy.md (§5 tests, §6), and skills db-performance, pest-testing.
Task: Phase 0 tasks 0.12 and 0.14.

Fix the bugs listed in 01 §5 and the P1 performance items in 12 §2. Complete the
test suite: flipped tests, Integrity/QueriesTest, and a MySQL CI job (migrations,
integrity, concurrency). Verify the [A] items in 14 §6 that Phase 0 owns (Breeze reset
response, unused permissions via grep, SQLite owner FK).
Then run the Phase 0 acceptance criteria from 13 one by one and report pass/fail with evidence.

Plan mode first; wait for approval. Finish with the quality-gate skill and composer test.
Afterwards update docs/erd.md from erd-proposed.md.

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

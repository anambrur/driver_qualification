# WP8: Platform area, support sessions, audit (tasks 0.7, 0.10, 0.11)

Split into three sessions if the plan is too large: 8a (0.7), 8b (0.10), 8c (0.11).

```
Read CLAUDE.md, 04-platform-administration.md, 08-database-schema.md (§3, §10),
14-open-questions.md (§1), 11-migration-strategy.md (§5 step 6), and skill security-checklist.
Task: Phase 0 tasks <0.7 | 0.10 | 0.11>.

0.7: platform_users + platform guard, /platform area (companies, plans, prices, features,
overrides, subscriptions, lookups, settings, staff with two-Owner minimum),
EmailIdentityService (named lock; an email cannot be both platform and tenant login),
PlatformOwnerBootstrap command (two Owners), audit viewer, Asset Transfer (PO-08).
Delete super-admin branches from tenant code.
0.10: support sessions: request, tenant Owner + second Platform Owner approval,
support user, banner, audit of every request, revoke; PO-02 and PO-07 run in read_write sessions.
0.11: audit log writer and the events listed in 08 §10.

Tests: Platform/SupportSessionTest, Platform/AssetTransferTest, Platform/EmailIdentityTest.
Plan mode first; wait for approval. Finish with the quality-gate skill.

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

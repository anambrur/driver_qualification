# WP7: Entitlements and limits (task 0.9)

```
Read CLAUDE.md, 05-plans-features-entitlements.md, 07-subscription-billing.md (§3-4),
and skills db-performance, multi-tenant-safety.
Task: Phase 0 task 0.9.

Build: App\Features\Feature enum + FeatureRegistrySync, Entitlements service (plan +
company_feature_overrides, cache-version invalidation), feature: middleware, @feature
Blade directive, limits with locking (only active hired drivers count toward max_drivers;
only office users toward max_users), read-only mode for lapsed/downgraded modules (still exportable).

Tests: Entitlements/*, Limits/ConcurrencyTest (MySQL: two parallel creates at limit-1 -> one success).

Plan mode first; wait for approval. Finish with the quality-gate skill.
Acceptance: disabling maintenance in a plan blocks its routes, menus and jobs within one request.

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

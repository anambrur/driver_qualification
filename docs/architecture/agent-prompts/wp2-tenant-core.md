# WP2: Tenant core (tasks 0.3, 0.4)

```
Read CLAUDE.md, docs/architecture/03-multi-tenant-architecture.md, 10-workflows.md (§1),
11-migration-strategy.md (§4-5), and skill multi-tenant-safety.
Task: Phase 0 tasks 0.3 and 0.4.

Build: CompanyProvisioner (the only code path that creates a company; used by registration
with company name, platform "create company", seeders, factories), TenantContext,
ResolveTenant middleware (priority before route bindings), BelongsToCompany global scope
(fail closed without context), TenantRule, TenantAware job middleware. Refactor
CompanyFilterTrait to read from TenantContext and remove its per-instance memo.
Keep existing controllers working.

Tests: Tenancy/ScopeFailClosedTest, Tenancy/JobContextTest, provisioner test
(7 system roles, settings, sequence, support user).

Plan mode first; wait for approval. Finish with the quality-gate skill.
Acceptance: suite green; a tenant model queried without context throws.

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

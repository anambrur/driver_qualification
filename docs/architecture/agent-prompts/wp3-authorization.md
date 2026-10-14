# WP3: Authorization (task 0.5)

```
Read CLAUDE.md, 06-roles-permissions.md, 03-multi-tenant-architecture.md,
docs/architecture/spikes/permission-scale-spike.php, and skills multi-tenant-safety, security-checklist.
Task: Phase 0 task 0.5.

Implement Spatie teams (team_id = company_id) with database-first checks: config,
extended models, CatalogueRegistrar bound in AppServiceProvider::boot(),
TenantPermissionResolver, hasPermissionTo overrides, Gate::before, permission catalogue,
role templates, RoleService, RoleAssignmentService, OwnershipService.
Rewrite PermissionSeeder (idempotent, never touches team roles) and add PlatformRoleSeeder.
Rename changed permissions in routes/web.php (e.g. companies.edit -> company.edit).

Tests: Authorization/EscalationTest, Authorization/ResolverTest (include the architecture
test banning $permission->roles for decisions), PermissionScaleTest (MySQL, 2,000 companies, p95 < 5 ms).

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

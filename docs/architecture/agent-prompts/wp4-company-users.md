# WP4: Company user management (task 0.6)

```
Read CLAUDE.md, 06-roles-permissions.md (§7), 10-workflows.md (§5), and skills
multi-tenant-safety, security-checklist.
Task: Phase 0 task 0.6.

Build: company invitations (company_invitations), users screen, roles screen (per company),
per-user permission grants (backend; UI comes in Phase 1.6). Owners and admins manage
users in their own company only; cross-company access returns 404.
Update UsersRoles tests that change by design (11 §5 table).

Plan mode first; wait for approval. Finish with the quality-gate skill.
Acceptance: an Owner can invite users, create roles and assign them; escalation tests pass.

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

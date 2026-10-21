# P1-2: Per-user permission grants screen (task 1.6)

```
Read docs/architecture/agent-prompts/README.md (rules and AUTO-FINISH), CLAUDE.md,
06-roles-permissions.md (§7 and the §8 escalation rules), 10-workflows.md §4–5, 02-requirement-matrix.md row 1.8.
Skills: multi-tenant-safety, security-checklist, laravel-conventions, seo-checklist.
Task: Phase 1 task 1.6. Package name: p1-2-user-grants.

Backend exists [V]: UserPermissionController store/destroy, RoleAssignmentService grant/revoke with the
escalation checks, routes users.permissions.store / destroy (routes/web.php:192-194), permission
permissions.grant under feature custom_roles. Build the UI only; change the backend only if a test shows a gap.
1. An "Extra permissions" card on the user edit page:
   - permissions the user has through roles, read-only, with the role name;
   - direct grants, each with Revoke (data-confirm);
   - an add control listing only what the actor may grant (the same grantable source and grouping
     the roles form uses), without the ones the user already holds.
2. Hide the card when the actor lacks permissions.grant or the company lacks custom_roles.
3. Remove the "backend only, UI in Phase 1.6" comments (UserPermissionController, web.php).
4. Tests: card shown or hidden by permission and feature; grant and revoke round trip; cannot grant what
   you don't hold; no self-edit; the Owner is locked; another company's user is 404; audit rows written;
   read-only company refused.

PENDING, recommended defaults:
- Add one permission at a time (select + Add) using the existing routes, or a checkbox set? Recommend one at a time; no backend change.
- Mark users with extra grants on the users list? Recommend a small "+N extra" badge.
- Owner hint when custom_roles is off? Recommend a short upgrade line linking to billing, Owners only.

Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

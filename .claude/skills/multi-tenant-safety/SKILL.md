---
name: multi-tenant-safety
description: Tenant isolation checklist. Use for any code that reads or writes company-owned data, roles/permissions, or driver logins.
---
Read `docs/architecture/03-multi-tenant-architecture.md` and `06-roles-permissions.md` first for the decided model (one company per user, Spatie teams, driver logins).
- Every query on tenant tables is scoped by company (global scope or explicit `where`). Never `Model::find($id)` from request input without scope.
- Set Spatie team context (`setPermissionsTeamId`) before role/permission checks, incl. in jobs, commands, and tests.
- Jobs/events carry `company_id`; re-establish context inside the job.
- Policies check company ownership, not only role.
- Unique rules/validation `exists` must be company-scoped.
- Files stored under company-prefixed paths; downloads authorize ownership.
- Every new tenant feature gets a cross-tenant access test (company A cannot see/modify company B -> 403/404).

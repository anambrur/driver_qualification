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
```

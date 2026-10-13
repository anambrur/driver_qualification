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
```

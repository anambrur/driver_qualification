# WP5: Isolation sweep (11 §5 steps 2-3)

```
Read CLAUDE.md, 03-multi-tenant-architecture.md, 11-migration-strategy.md (§5), and skills
multi-tenant-safety, db-performance.
Task: add BelongsToCompany to each tenant model with its isolation test, in this order:
drivers and their documents, fleet, maintenance, asset groups. Remove applyCompanyFilter
calls afterwards. Replace authorizeCompanyAccess with policies, controller by controller;
foreign ids return 404.
Add Tenancy/IsolationMatrixTest generated from the route list: for every tenant route
with an id parameter, a user of company B gets 404 on company A's id.
Update AccessControl/RouteGuardMatrixTest (tenant routes need ResolveTenant).

Work one model group at a time; suite must be green after each. Plan mode first.
Finish with the quality-gate skill.
```

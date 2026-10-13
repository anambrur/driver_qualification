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
```

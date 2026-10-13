# WP8: Platform area, support sessions, audit (tasks 0.7, 0.10, 0.11)

Split into three sessions if the plan is too large: 8a (0.7), 8b (0.10), 8c (0.11).

```
Read CLAUDE.md, 04-platform-administration.md, 08-database-schema.md (§3, §10),
14-open-questions.md (§1), 11-migration-strategy.md (§5 step 6), and skill security-checklist.
Task: Phase 0 tasks <0.7 | 0.10 | 0.11>.

0.7: platform_users + platform guard, /platform area (companies, plans, prices, features,
overrides, subscriptions, lookups, settings, staff with two-Owner minimum),
EmailIdentityService (named lock; an email cannot be both platform and tenant login),
PlatformOwnerBootstrap command (two Owners), audit viewer, Asset Transfer (PO-08).
Delete super-admin branches from tenant code.
0.10: support sessions: request, tenant Owner + second Platform Owner approval,
support user, banner, audit of every request, revoke; PO-02 and PO-07 run in read_write sessions.
0.11: audit log writer and the events listed in 08 §10.

Tests: Platform/SupportSessionTest, Platform/AssetTransferTest, Platform/EmailIdentityTest.
Plan mode first; wait for approval. Finish with the quality-gate skill.
```

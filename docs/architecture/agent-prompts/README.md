# Agent prompts: Phase 0 work packages

One package = one branch = one fresh Claude session. Run them in order.

## Workflow

Only two manual steps: start `claude` in Plan mode, paste the prompt, approve the plan.
Every prompt ends with an AUTO-FINISH block, so the agent creates the branch, runs pint, phpstan,
the filtered and full tests, the code-review skill, commits, and merges into local `main` only if
every gate is green. It never pushes.

```bash
claude        # Shift+Tab for Plan mode, paste wpN-*.md prompt, approve
```

If a gate fails the agent stays on the branch and reports; fix with a follow-up prompt, then say "re-run AUTO-FINISH".

## Order

| WP | File | Roadmap tasks |
|---|---|---|
| 1 | [wp1-schema.md](wp1-schema.md) | 0.1 (rest), 0.2, 0.13 |
| 2 | [wp2-tenant-core.md](wp2-tenant-core.md) | 0.3, 0.4 |
| 3 | [wp3-authorization.md](wp3-authorization.md) | 0.5 |
| 4 | [wp4-company-users.md](wp4-company-users.md) | 0.6 |
| 5 | [wp5-isolation-sweep.md](wp5-isolation-sweep.md) | 11 §5 steps 2–3 |
| 6 | [wp6-billing.md](wp6-billing.md) | 0.8 |
| 7 | [wp7-entitlements.md](wp7-entitlements.md) | 0.9 |
| 8 | [wp8-platform.md](wp8-platform.md) | 0.7, 0.10, 0.11 |
| 9 | [wp9-fixes-tests.md](wp9-fixes-tests.md) | 0.12, 0.14 |

## Rules for every session

- Plan mode first; wait for approval before code (CLAUDE.md).
- If docs conflict with code or each other, stop and ask.
- After each package, ask the agent to update `14-open-questions.md` and the README decision log if anything changed.
- Phase 1+ starts only after WP9 is green on SQLite and MySQL.

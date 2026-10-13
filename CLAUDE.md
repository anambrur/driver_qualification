# Driver Qualification (DOT compliance SaaS)

Laravel 13, PHP 8.3+, Spatie permission (teams), Pest, Larastan lvl 5, Pint, Stripe, DataTables.

## Commands
- Test: `php artisan test --filter=<name>` (full: `composer test`)
- Style: `vendor/bin/pint --dirty`
- Static: `vendor/bin/phpstan analyse --memory-limit=1G <changed paths>`

## Architecture
- Multi-tenant: one company per user, Spatie teams, driver logins. Details: `docs/architecture/03-multi-tenant-architecture.md`, `06-roles-permissions.md`, `12-performance-plan.md`.
- Logic lives in `app/Services/<Domain>`. Controllers stay thin. Repositories only for complex domains (Driver, Compliance, Billing); see skill `repository-service-pattern`.

## Rules
- Every query on tenant data must be company-scoped. Every route authorized.
- Review before implementation: label claims with evidence, mark unknowns PENDING, get approval before final artifacts or code.
- Read only the files you need; do not scan the repo or `vendor/`, `node_modules/`.
- Match surrounding code style. No speculative abstractions, no extra docs/comments.
- Before finishing PHP changes: run `quality-gate` skill steps on changed files only.

## Skills (load on demand)
laravel-conventions, repository-service-pattern, multi-tenant-safety, security-checklist, db-performance, pest-testing, quality-gate, queue-and-jobs

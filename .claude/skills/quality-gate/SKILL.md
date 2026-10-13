---
name: quality-gate
description: Pre-finish checklist for PHP changes. Use before reporting a coding task as done.
---
Run on changed files only; keep output short.
1. `vendor/bin/pint --dirty`
2. `vendor/bin/phpstan analyse --memory-limit=1G <changed files> --error-format=raw` (level 5; fix new errors, don't baseline-bloat)
3. `php artisan test --filter=<related>` (full suite only if shared code changed)
4. Report failures verbatim; don't claim done if any step failed or was skipped.

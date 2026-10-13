---
name: laravel-conventions
description: Laravel 13 code style and structure rules. Use when writing or editing controllers, requests, models, routes, or migrations.
---
- `declare(strict_types=1)` not required; add return types and typed properties everywhere (relations: `BelongsTo`, `HasMany`).
- Controller: validate via FormRequest, authorize via Policy/`$this->authorize`, call a Service, return view/resource. No business logic, no raw queries.
- Models: `$fillable` explicit (never `$guarded = []`), `casts()` for dates/enums/json, scopes for reusable filters, no logic that needs I/O.
- Use backed enums for statuses/types, `Resource` classes for JSON, named routes, route model binding, `config()` not `env()` outside config files.
- Migrations: FK constraints, indexes on FK and filter columns, `down()` reversible. This repo consolidates changes into create-table files (fresh rebuild).
- Prefer Collections/Eloquent over loops with queries; early returns; small methods; no dead code.

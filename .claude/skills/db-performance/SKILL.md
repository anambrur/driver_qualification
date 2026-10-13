---
name: db-performance
description: Query and performance rules. Use when writing Eloquent queries, listings, DataTables, reports, migrations with indexes, or caching.
---
Plan reference: `docs/architecture/12-performance-plan.md`.
- No N+1: `with()`/`withCount()`; check loops touching relations. Prefer `Model::preventLazyLoading(! app()->isProduction())`.
- `select()` only needed columns for lists; `exists()` not `count() > 0`; aggregate in SQL, not PHP.
- Large sets: `chunkById`/`lazyById`/`cursor`; always paginate lists.
- Index FK, tenant (`company_id`), status, and frequently filtered/sorted columns; composite index order = equality first, then range/sort.
- DataTables: server-side, eager load, search only indexed columns, no `->get()` then filter.
- Multi-step writes in `DB::transaction`; `lockForUpdate` for counters/balances.
- Cache only stable, expensive reads with tenant-aware keys and explicit invalidation; no caching before measuring.

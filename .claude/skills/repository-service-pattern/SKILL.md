---
name: repository-service-pattern
description: When and how to use Repository + Service layers here. Use when adding data-access or business logic for Driver, Compliance, or Billing, or deciding whether a repository is needed.
---
**Default:** Controller -> Service -> Eloquent (scopes). No repository.

**Add a repository only when** the domain (Driver, Compliance, Billing) has complex or reused queries (filters, reports, aggregates) used by 2+ callers, or needs swapping/mocking.

Structure:
- `app/Repositories/<Domain>/<Name>RepositoryInterface.php` + `Eloquent<Name>Repository.php`
- Bind in a `RepositoryServiceProvider`; inject the interface by constructor.
- Repository: queries only (find, filter, paginate, eager loads, tenant scope). Returns models/collections/paginators. No validation, HTTP, or business rules.
- Service: business rules, transactions, events/jobs, calls repositories. Existing services in `app/Services/*` stay; refactor only when touching them.
- Don't wrap trivial `find/create/update`; don't create a repository per model; no generic `BaseRepository`.

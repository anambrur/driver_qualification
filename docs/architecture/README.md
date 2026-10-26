# DriverFilesHub target architecture

This document set describes how DriverFilesHub becomes a company-based, multi-tenant SaaS application. That is the foundation the client's DOT Doc Hub use cases (trips, mobile app, tracking, pay, QuickBooks, messaging) will be built on.

- Status: **Phase 0 done** (main `67bb97c`, 21 October 2026). **Phase 1 done** (23 October 2026, [13](13-implementation-roadmap.md#phase-1-quick-wins)). **Phase 2** (trips and loads, web) runs as packages P2-1 to P2-7 in [agent-prompts/](agent-prompts/README.md).
- Written: 11 October 2026, against commit `d1d4d8d`; decisions applied 13 October 2026.
- Implementation follows [13-implementation-roadmap.md](13-implementation-roadmap.md).

## Documents

| # | Document | What it answers |
|---|---|---|
| 01 | [Current architecture assessment](01-current-architecture-assessment.md) | What exists today, what is solid, and what is risky, with file references |
| 02 | [Requirement matrix](02-requirement-matrix.md) | Each client requirement: verified status, the code involved, and the database changes, dependencies and priority it needs |
| 03 | [Multi-tenant architecture](03-multi-tenant-architecture.md) | How companies are isolated, and where isolation could fail |
| 04 | [Platform administration](04-platform-administration.md) | Platform Owner, platform staff, support sessions |
| 05 | [Plans, features and entitlements](05-plans-features-entitlements.md) | How plans decide what a company can use |
| 06 | [Roles and permissions](06-roles-permissions.md) | Spatie teams per company, escalation rules |
| 07 | [Subscription and billing](07-subscription-billing.md) | Company-owned subscriptions on Stripe |
| 08 | [Database schema](08-database-schema.md) | Every table: ownership, keys, constraints, indexes |
| 09 | [ERD audit](09-erd-audit.md) | Problems in the current schema and how each is fixed |
| — | [Proposed ERD](../erd-proposed.md) | Mermaid diagrams of the target schema (the current one stays in [../erd.md](../erd.md)) |
| 10 | [Workflows](10-workflows.md) | End-to-end flows, from registration to purge |
| 11 | [Migration strategy](11-migration-strategy.md) | Order of migrations, seeders, test changes, verification, rollback |
| 12 | [Performance plan](12-performance-plan.md) | Prioritised database and application improvements |
| 13 | [Implementation roadmap](13-implementation-roadmap.md) | Phases, tasks, dependencies, acceptance criteria |
| 14 | [Decisions and open questions](14-open-questions.md) | Answers from 13 Oct 2026, investigation notes, the few remaining questions |
| — | [Permission scale spike](spikes/permission-scale-spike.php) | The load-test script behind the permission decision |

## Labels used in every document

| Label | Meaning |
|---|---|
| **[V]** | Verified in the repository. A file or line reference is given. |
| **[R]** | Recommendation |
| **[A]** | Assumption, not yet verified |
| **[L]** | Legal, compliance or external-policy item. The client or their counsel must confirm it. |
| **PENDING** | A business decision that is still open. The documents use the recommended default and say so. |

## Decision log

### Confirmed decisions

| Decision | Source |
|---|---|
| There is no real customer data yet. Schema changes go into the existing create-table migrations, and environments are rebuilt with `migrate:fresh --seed`. Once the first production deploy happens, migrations become additive only. | Product owner, 11 Oct 2026; PO-19 |
| Drivers get their own login, linked to their driver record. | Product owner, 11 Oct 2026 |
| A user belongs to exactly one company (`users.company_id`). | Product owner, 11 Oct 2026 |
| Roles and permissions use Spatie Permission with teams turned on (`team_id = company_id`). | Product owner, 11 Oct 2026 |
| Permission checks are **database-first**: one indexed query per request in the user's own team, with no global role cache. Spatie stays for the schema, management APIs and middleware. Chosen after a load test at 2,000 and 5,000 companies. | Product owner instruction 13 Oct 2026; [06](06-roles-permissions.md) §2 |
| Platform staff have a separate `platform_users` table and `platform` guard. An email can't be both a platform and a tenant login. | D1b, D3a (13 Oct 2026) |
| `companies.email` is no longer synced from the owner's login; there is a separate `billing_email`. Part of PO-22 is reversed. | D3b (13 Oct 2026) |
| The three typed compliance-document tables stay. Production database: MySQL 8.0.16 or later. | D5a, D7a (13 Oct 2026) |
| PO-02 and PO-07 run in `read_write` support sessions. PO-08 becomes the audited platform Asset Transfer. | D11 (13 Oct 2026) |
| Support sessions need the company Owner **and** a second Platform Owner. At least two Platform Owners must exist. | Q1 (13 Oct 2026) |
| A lapsed subscription is read-only, after a 7-day grace period. Plan edits apply immediately (with overrides for grandfathering), downgrades at period end, and lost modules are read-only and exportable. | Q2, Q3 (13 Oct 2026) |
| The rest of the defaults are confirmed: limits counting (Q4), trial plan (Q5), one load per trip (Q6), folders, PDF conversion and ZIP (Q8), C1, C2, C4–C6, C8–C10. Daily-threshold overtime (C3). | 13 Oct 2026; [14](14-open-questions.md) |
| Lookups (vehicle types, fuel types, equipment types, vehicle groups, maintenance categories, document types) are global, and only platform staff edit them. | LKP-01 |
| Driver email is unique per company. | APP-07 |
| VIN and unit number are unique per company. | PO-10, FLT-02 |
| Licence plates are not unique; the plate state (US or Canada) is required with a plate number; the asset photo is jpg, png or webp up to 5 MB, served only through `admin.{vehicle,trailer}.photo`. | P1-1, 21 Oct 2026; [14](14-open-questions.md) §2 |
| Direct per-user grants are managed one at a time on the user edit page, never on your own account or the Owner's, and only for permissions the actor holds and the plan includes. Revoking stays possible after a downgrade. | P1-2, 21–22 Oct 2026; [14](14-open-questions.md) §2 |
| Google and Facebook sign-in never links an account by email; users connect a provider from the profile. Providers are off until their env keys are set and Platform > Settings > Site switches them on. Social sign-up uses the registration path with a random password; only Google's verified flag marks the email verified. The provider switches were added to `site_settings` by an alter migration, accepted because that table has no CHECK. | P1-3, 22 Oct 2026; [14](14-open-questions.md) §2 |
| Each driver (DQ file), vehicle and trailer has one standard folder; custom folders nest up to 5 levels inside it or at company level and take their parent's binding. A document's subject is its folder's binding. Only empty folders are deleted (soft, the name is freed); documents move only within one binding. | P1-4, 22 Oct 2026; [14](14-open-questions.md) §2, [08](08-database-schema.md) §8 |
| A photo uploaded into a folder becomes a one-page PDF in a queued job (EXIF fixed, longest side 3000 px); the image stays as the document's original and both count toward storage. On failure the image stays. Platform switch `DOCUMENTS_CONVERT_IMAGES`. | P1-5, 23 Oct 2026; [14](14-open-questions.md) §2, [08](08-database-schema.md) §8 |
| ZIP export of a driver or the whole company: queued per company on its own `exports` connection, everything attached to the entity, ready 7 days, outside the storage quota, audited requests and downloads, also for a read-only company. Cap 2 GB. Requesting or downloading a ZIP also needs `drivers.view`, and `folders.view` when the company has folders. | P1-6, 23 Oct 2026; [14](14-open-questions.md) §2, [08](08-database-schema.md) §12 |
| Phase 1 accepted: the four acceptance criteria pass with named tests, the "perf group" is the `scale` group, and smoke checks cover social sign-up and per-user grants. Only Phase 1 statuses were changed in the docs. | P1-7, 23 Oct 2026; [14](14-open-questions.md) §2 |
| The map is free: Leaflet + OpenStreetMap tiles and a server-side geocoder; `place_source` + `place_ref` replace `google_place_id`; planned miles are entered by hand. Phase 2 is web and office staff only, with a new `inspections.manage` permission; per-company integer trip numbers; the pre-trip gate applies only with `pre_trip_inspections`. | Phase 2 plan, 23 Oct 2026; [14](14-open-questions.md) §2 |
| Shippers and the address picker: shippers are not unique by name, inactive ones are kept but not offered, deletes are soft and audited. Address search goes through a server-side `Geocoder` (Photon by default, Nominatim as the second driver, `none` for manual entry), US and Canada only, on a button, cached 30 days and held to 1 request per second for the whole app; only `place_source` + `place_ref` are stored. | P2-1, 26 Oct 2026; [14](14-open-questions.md) §2, §4 |
| Companies and owners are deactivated, never cascade-deleted. | PO-13, PO-17 |
| Policy PDFs are per company, with a platform default as fallback. | PO-14 |
| One free trial per verified email. | PO-15 |
| The Stripe account is shared with another app, so events with a foreign `app_host` are ignored. | PO-18 |
| WP6 billing defaults: a lapsed company stays `read_only` (`none` only if it never had a subscription); read-only denies `is_write` permissions except `billing.*`; one trial per email is enforced by unique `companies.trial_email`; past-due grace runs from `current_period_start`; company plan changes go through the Stripe Billing Portal (downgrades scheduled at period end in its settings), the platform "move to price" is immediate; checkout and trial are POST. Platform billing screens move to `/platform` in WP8. | WP6 plan approval, 14 Oct 2026; [07](07-subscription-billing.md) |
| WP8b support sessions: the Owner approves on `/support-access` and a second Platform Owner on `/platform/support-sessions`; 24-hour request window; one active session per company; the session runs as the company's support user with a locked Support role, and every request is audited as the staff member; PO-02 and PO-07 work inside a `read_write` session with no special case. | WP8b plan approval, 15 Oct 2026; [04](04-platform-administration.md) §4 |
| WP8a platform area: `/platform` on the `platform` guard (30-minute idle timeout, no "remember me"); new staff and new company owners set their first password through a reset link; `platform:bootstrap-owners` creates the first two Platform Owners once; `EmailIdentityService::claim()` holds the email lock across check and insert; lookup lists are platform-only (tenants keep the document-type opt-out page); Stripe Price ids are entered by hand; Asset Transfer copies files under the target's prefix and refuses a unit no or VIN the target already has, retired assets included. Platform 2FA is deferred. | WP8a plan approval, 15 Oct 2026; [04](04-platform-administration.md) |
| Every other answered item in [../testing/PRODUCT_DECISIONS.md](../testing/PRODUCT_DECISIONS.md) | PO-01 … PO-22 |

### Rules this document set supersedes

| Rule | New rule | Why |
|---|---|---|
| PRODUCT_DECISIONS rule 1.6: "new migrations only, never edit an existing one" | Until the first production deploy, edit the create-table migrations; after it, additive only | The product owner confirmed there is no data. Commit `d1d4d8d` already consolidated the migrations. |

### Decisions still open

[14-open-questions.md](14-open-questions.md) §3:
- Retention periods [L]: the 08 §15 defaults apply until counsel changes them (23 Oct 2026).
- The MySQL 8.0.16 run before go-live.

The other questions were answered on 23 Oct 2026 (14 §2).

## Glossary

| Term | Meaning |
|---|---|
| Tenant / company | One carrier account, a `companies` row. Every tenant record belongs to exactly one company. |
| Platform | The SaaS operator: Platform Owner and platform staff. They have their own `platform_users` table and `platform` guard. |
| Owner | The tenant user in `companies.owner_user_id`. Holds the system Owner role and is the billing account holder. |
| Driver user | A tenant user linked to a `drivers` row through `drivers.user_id`. Holds the system Driver role. |
| Feature | A capability listed in the code feature registry and synced to the `features` table: a module (e.g. `trips`) or a limit (e.g. `max_drivers`). |
| Entitlement | What a company may use. Comes from its plan, adjusted by platform overrides. |
| Access state | `full`, `grace`, `read_only` or `blocked`, worked out from the company status and its subscription. |
| Support session | Time-limited, approved and audited access by platform staff to one company's data. |
| DQ file | The driver qualification file 49 CFR 391.51 requires a carrier to keep for each driver. |

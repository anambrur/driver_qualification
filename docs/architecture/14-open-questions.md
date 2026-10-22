# 14 · Decisions, open questions and assumptions

The product owner answered the questions from the 11 October draft on **13 October 2026**. This file records those answers, how each one was applied, and the few items that are still open.

## 1. Decided: platform, data and security

| ID | Decision | Applied in |
|---|---|---|
| D1a | Design and test for **2,000+ companies**. The permission system must not depend on Spatie's global cache. | [06](06-roles-permissions.md) §2. Load-tested at 2,000 and 5,000 companies; checks are database-first. |
| D1b | Separate `platform_users` table and `platform` guard | [04](04-platform-administration.md) §2, [08](08-database-schema.md) §3 |
| D3a | An email can **not** be both a platform login and a tenant login | [04](04-platform-administration.md) §2: `EmailIdentityService` with a named lock, plus the nightly integrity query |
| D3b | Stop syncing `companies.email` from the owner's login email. Keep a separate `billing_email`. | [08](08-database-schema.md) §4, [09](09-erd-audit.md) F4 |
| D5a | Keep the three typed compliance-document tables | [08](08-database-schema.md) §6, §8 |
| D7a | MySQL 8.0.16 or later in production. The spike ran on MySQL 9.3. | [08](08-database-schema.md) §1 |
| D11 | PO-02 and PO-07 run inside a `read_write` support session. PO-08 becomes an audited platform **Asset Transfer**. | [04](04-platform-administration.md) §6–7 |
| Q1 | **Both parties** authorize every support session: the company Owner **and** a second Platform Owner (four-eyes). At least **two** Platform Owners must exist. | [04](04-platform-administration.md) §3–4. There is no break-glass path without the tenant's approval. |
| Q2 | When a subscription lapses, the company gets **read-only** access. Grace period: 7 days. | [07](07-subscription-billing.md) §4 |
| Q3 | Plan edits apply **immediately**, with overrides for grandfathering. Upgrades apply immediately; **downgrades at the end of the period**. A module the company loses stays **read-only and exportable**. | [05](05-plans-features-entitlements.md) §7, [07](07-subscription-billing.md) §3 |
| Q4 | Default kept: only active (hired) drivers count toward `max_drivers`. Only office users count toward `max_users`; driver logins and support users don't. | [05](05-plans-features-entitlements.md) §5 |
| Q5 | Default kept: the trial grants the top tier, matching today's "Access to all features" trial | [07](07-subscription-billing.md) §5 |

## 2. Decided: client requirements

| ID | Decision | Applied in |
|---|---|---|
| Q6 | One load per trip | [08](08-database-schema.md) §9 |
| Q8 | Standard folders per entity (DQ file, vehicle, trailer, trip). Images uploaded to folders are converted to PDF and the original is kept. A ZIP holds everything attached to the entity. | [08](08-database-schema.md) §8, [10](10-workflows.md) §7, §9 |
| C1 | Default kept: per-mile and hourly rates are stored per driver. How they combine was left to engineering judgment (Phase 4 instruction): **mileage pay on trip miles plus hourly pay on non-driving on-duty time**. A driver with only an hourly rate is paid hourly for all work time. | [08](08-database-schema.md) §11 |
| C2 | Daily profit and loss includes variable costs only | [08](08-database-schema.md) §11 |
| C3 | Overtime and double overtime use **daily thresholds**. After investigation (section 5): configurable per company, defaults 8 h → OT ×1.5 and 12 h → DT ×2.0, optional weekly threshold, and a per-driver exemption flag | [08](08-database-schema.md) §11, [10](10-workflows.md) §15 |
| C4 | QuickBooks Online: employees and time activities. Per-mile and premium amounts go into a payroll summary export (section 5). | [08](08-database-schema.md) §12, [13](13-implementation-roadmap.md) Phase 4 |
| C5 | A trailer's location comes from the truck on its active trip | [08](08-database-schema.md) §9 |
| C6 | Drivers and staff submit time-off requests; an Owner or Administrator approves | [08](08-database-schema.md) §12 |
| C8 | "Load type" in the year-end report = the trip's product | [08](08-database-schema.md) §9 |
| C9 | The accident register report is generated from accident trip events. Explicit columns hold the register fields. | [08](08-database-schema.md) §9 (`trip_events`) |
| C10 (also given as "Q10") | Pre-trip checklist only; it is not treated as a DVIR | [08](08-database-schema.md) §9 |
| Phase 3 | Scope unchanged | [13](13-implementation-roadmap.md) |
| P1-1 (3.5) | Plates (21 Oct 2026, defaults accepted): no uniqueness on the plate (plates are reissued; one number can exist in two states); the plate state is required with a plate number and must be a US state or Canadian province; photo jpg, png or webp up to 5 MB, no PDF conversion; the plate shows on the vehicle and trailer lists and on the new vehicle and trailer pages only. The pages have no Edit button (editing stays in the list's dialog). Vehicle and trailer validation moved to FormRequests. | `app/Http/Requests/Fleet/`, `FleetAssetService`, `admin/{vehicle,trailer}/show` |
| P1-2 (1.8) | Per-user grants (21 Oct 2026, defaults accepted): an Extra permissions card on the user edit page adds one permission at a time (select + Add) on the existing routes, with no backend change; the add list is the same grantable source as the role form, minus what the user already holds. The card is hidden without `permissions.grant` or Custom roles, and on your own page and the Owner's page. A direct grant the actor doesn't hold has no Revoke. The users list shows a "+N extra" badge. Without Custom roles the Owner sees one upgrade line linking to billing. Follow-up (22 Oct 2026): a grant must work under the plan (`Entitlements::permits`, the same rule as PermissionGate step 4: write permissions need the feature on), checked in `grantDirect` and used to filter the add list; after a downgrade the card stays in revoke-only mode while direct grants remain, and `users.permissions.destroy` is guarded by `users.edit` (the service still requires `permissions.grant` held), so grants can always be removed. | `UserController::edit`, `admin/users/edit`, `admin/users/index` |

## 3. Still open

| ID | Question | Current handling |
|---|---|---|
| Co-driver | Asset groups need a co-driver for team driving: yes or no? This was "Q10" in the earlier list. The 13 October answer under "Q10" covered the DVIR question (C10), so this one is still unanswered. | Default kept, **PENDING**: `asset_groups.co_driver_id` |
| C7 | The client's list has an empty bullet after "miles traveled and the distance to the destination". Investigated: the PDF text at that bullet contains only a zero-width space (`pdftotext` output, line 51). There is no hidden or cut-off text in the file. | **Ask the client** whether a requirement is missing. Nothing is added until then. |
| Emergency support access | Q1 requires both parties' approval, so there is no access when the company Owner can't be reached. | Not built. If it's ever needed (for example, a legal request), it becomes a new decision. |
| Retention periods [L] | See section 4 | Defaults in [08](08-database-schema.md) §15 until counsel confirms |
| WP1 hand-offs (13 Oct 2026) | Who owns: the `stored_files` write path and the wizard's medical-card upload creating the `medical_examiner_certificate` compliance document (F17); `PolicyPdf::resolveFor()` falling back to the `site_settings` platform defaults; `asset_transfers` (08 §3); `company_invitations` (group N). | `asset_transfers` done in WP8a; `company_invitations` done in WP4. Proposed: WP9 for the upload write path, wizard and policy fallback (the platform default policy screen went with the super-admin). WP8a added only `StoredFileCopier`, for transfers. **PENDING** |
| Platform 2FA (WP8a) | 04 §2 recommends 2FA for every platform user [R]. The columns exist; no TOTP library is installed. | Deferred by the product owner (15 Oct 2026). Must ship before go-live. **PENDING** |
| Stripe Price creation (WP8a) | `/platform/plans` stores a price with a hand-entered `stripe_price_id`; creating the Price through the API would need `plans.stripe_product_id`. | Default: entered by hand. **PENDING** |
| Site-settings fields (WP8a) | 04 §8 identity, theme, SEO and mail-sender columns have no UI yet; only trial plan, trial days, grace days and the support-session length were added. | Default: later WP. **PENDING** |
| Transfer back to the source (WP8a) | A transferred asset stays soft-deleted at the source, and `(company_id, unit_no)` / `(company_id, vin)` include trashed rows, so moving it back to the source is refused. | Default: refuse with a clear message. **PENDING** |
| Company purge (WP8a) | `platform.companies.purge` exists but the purge job is not built. | Waits for the retention periods [L]. **PENDING** |
| Support-session defaults [A] (WP8b) | 24-hour request window; one active session per company; session length is always the site maximum; platform approvers are not emailed (they see `/platform/support-sessions`); the per-request audit stores method, route, path and status only. | Applied. Change if the product owner disagrees. |
| Document deletes in support sessions (WP8b) | 04 §4.3 withholds "deleting drivers or documents" from `read_write`, but the routes used `drivers.edit` / `fleets.edit`. | Done: driver-document deletes need `driver-documents.delete`; vehicle and trailer document deletes need the new `fleets.delete`. Both are withheld from Support (read/write). |
| Platform defaults [A] (WP8a) | 30-minute platform idle timeout; new owners and staff set their password through a reset link; tenant lookup list pages removed (document types stay for opt-outs); the transfer picker shows unit no and VIN to staff with `platform.assets.transfer` without a support session; `converted_from_file_id` is not carried over by a transfer. | Applied. Change if the product owner disagrees. |
| MySQL 8.0.16 run | WP1 was verified on SQLite 3.45 and MySQL 9.3 only (no 8.0 server available locally). | Run `migrate:fresh --seed`, `integrity:check` and the Schema/Integrity tests on 8.0.16 before go-live. **PENDING** |
| Scheduler and Octane (PO-20) | Is the scheduler running every minute in production? Will the app ever run on Octane? | The design is Octane-safe. The scheduler must be confirmed before go-live. |

## 4. Legal and compliance items for the client or counsel [L]

| Item | Why it matters |
|---|---|
| Retention periods: DQ file (employment + 3 years, 49 CFR 391.51); maintenance records (1 year, plus 6 months after the vehicle leaves the carrier, 396.3); accident register (3 years, 390.15); financial and payroll records; location pings; audit logs | When the purge job may delete data |
| Read-only access after a subscription lapses (Q2) | DQ files must still be producible in an audit |
| Medical examiner certificate and CDL expiry tracking (391.41, 391.51) | Added to the compliance engine ([09](09-erd-audit.md) F17) |
| Overtime eligibility per driver: the FLSA Motor Carrier Act exemption (§13(b)(1)), its small-vehicle exception (10,000 lb or less), and state daily-overtime rules and their exemptions (section 5) | The company sets `drivers.overtime_exempt`. The software doesn't decide it. |
| Regular-rate method for mixed per-mile and hourly pay (29 CFR 778.111, piece-rate) | The overtime premium formula in [08](08-database-schema.md) §11 |
| Timesheets are not hours-of-service records (Part 395 / ELD) | The product must not suggest HOS compliance |
| Employee location-tracking notice and consent, which vary by state | Phase 3 |
| SSN and other personal data: encryption at rest exists [V]; access is audited | — |
| App Store rule 4.8 (Sign in with Apple), Facebook Login app review, Google Maps Platform terms | Phase 1 and Phase 3 |

## 5. Investigation notes behind C3 and C4

### Overtime (C3)

- **Federal FLSA**
  - Overtime applies after 40 hours in a workweek. There is no daily overtime and no double time.
  - Drivers, driver's helpers, loaders and mechanics whose duties affect the safety of motor vehicles in interstate commerce are exempt under §13(b)(1), the Motor Carrier Act exemption.
  - The exemption does **not** apply in any workweek in which the employee's work involves vehicles of 10,000 lb or less, the small-vehicle exception in force since 2008.
  - Sources: DOL WHD Fact Sheet #19 and Field Assistance Bulletin 2010-2.
- **States with daily overtime**
  - California: over 8 h at ×1.5, over 12 h at ×2.
  - Alaska: over 8 h.
  - Colorado: over 12 h.
  - Nevada: over 8 h, for employees earning under 1.5× the state minimum wage.
  - Where both daily and weekly rules apply, the employer pays whichever gives more overtime, without counting the same hour twice.
- **California exemption for DOT-regulated drivers**
  - IWC Wage Order 9 §3(L) exempts drivers whose hours are regulated by 49 CFR 395.1–395.13 (or California's own Title 13 hours-of-service rules) from the order's overtime section.
  - Those drivers remain covered by its meal, rest-break and wage rules (DLSE opinion letters 2001-02-04 and 2002-12-13).
  - [L] Confirm against the current wage order text.
- **Implementation consequence**
  - The thresholds are company settings: daily OT and DT, defaulting to the user's choice of 8 h and 12 h, plus an optional weekly threshold.
  - Each driver has an overtime-exempt flag and basis, chosen by the company. Minutes are still split into regular, OT and DT for exempt drivers, but no premium is paid.
  - The California 7th-consecutive-day rule is **not** built. [L] Add it only if a California company needs it.

### QuickBooks (C4)

- The Accounting API covers **Employee** (a name and contact record) and **TimeActivity** (hours).
- Compensation and pay data sit behind Intuit's separate **Payroll API**. It needs a payroll scope, is a premium or partner-gated product, is not available in the sandbox, and needs extra onboarding for sensitive fields.
- **Decision for v1:**
  - Sync drivers as Employees.
  - Push approved timesheet hours as TimeActivity, with regular, OT and DT on separate lines.
  - Export a payroll summary (CSV and PDF) per payroll run with the mileage pay and overtime premiums, for the payroll administrator to key into QuickBooks Payroll.
  - Apply for the Payroll API only if the client wants pay amounts pushed automatically. [A] Re-check Intuit's current terms at the start of Phase 4.
- **Contractor drivers.** The `drivers` table already holds W-9 fields (`business_name`, `employer_identification_number`, `federal_tax_classification` [V]), so contractor drivers exist. They are excluded from payroll and overtime through `drivers.worker_type`. Settlements for contractors are not in the client's requirements, so they are not built.

Sources:
- [DOL WHD Fact Sheet #19, Motor Carrier Exemption](https://www.dol.gov/whd/regs/compliance/whdfs19.htm)
- [DOL Field Assistance Bulletin 2010-2](https://www.dol.gov/agencies/whd/field-assistance-bulletins/2010-2)
- [California DLSE overtime exemptions FAQ](https://dir.ca.gov/dlse/faq_overtimeexemptions.htm)
- [DLSE opinion letter 2001-02-04](https://www.dir.ca.gov/dlse/opinions/2001-02-04.pdf)
- [DLSE opinion letter 2002-12-13](https://www.dir.ca.gov/dlse/opinions/2002-12-13.pdf)
- [State overtime laws summary](https://www.ontheclock.com/State-Overtime-Laws-And-Rules)
- [Intuit Payroll and Time API: scopes](https://developer.intuit.com/app/developer/payroll-time/docs/learn/learn-about-scopes)

## 6. Assumptions [A] still to verify

| Assumption | Verify by |
|---|---|
| MySQL CHECK, generated-column and referential-action behaviour in production matches the design | Phase 0 migration tests on the MySQL CI job (the spike already confirmed composite FKs on MySQL 9.3) |
| Laravel can add the companies → users owner FK on SQLite after both tables exist | Phase 0 |
| The default Breeze password-reset response reveals whether an account exists | Check views and lang strings in Phase 0 |
| `submitted`, `under_review` and `approved` driver statuses are labels with no transition | Before the status split |
| `driver_documents.document_path`, `permission.*` and `asset-histories.*` are unused | Grep in Phase 0 |
| Default company time zone (`America/Chicago`) | Ask for it during registration instead |
| Intuit Payroll API access terms | Start of Phase 4 |
| Expense categories (toll, lumper, scale, parking, repair, other) | Confirm with the client in Phase 4 |

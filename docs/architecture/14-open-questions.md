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
| P1-3 (1.4) | Google and Facebook sign-in, web (22 Oct 2026, defaults accepted): no auto-link by email; an existing tenant email and a platform email get the same message (sign in with the password, then connect from the profile). A provider is offered only when its keys are in the env **and** its switch in Platform > Settings > Site is on (default off). Invitations stay password-based. Driver-role users follow the password-login rules. A Google email counts as verified when Google says so; a Facebook email is always verified by our own link. A new email gets a short finish form (name, company name), then the registration path (`EmailIdentityService::claim` + `CompanyProvisioner`) with a random password; the provider profile waits in the session for 15 minutes, never the token. "Never set a password" = an `auth.social_signup` audit row and no later `auth.password_reset` / `auth.password_changed`; while true, the profile warns and the last provider can't be disconnected. The password login now also sets `last_login_at`. The two provider switches were added to `site_settings` by an alter migration (`2026_10_22_000300`): an accepted exception to the create-migration rule, because `site_settings` has no CHECK for SQLite to drop. | `SocialLoginService`, `SocialProviders`, `SocialLoginController`, `ConnectedAccountController`, `profile/partials/connected-accounts` |
| P1-4 (2.1–2.3) | Folders and documents (22 Oct 2026, defaults accepted): one standard folder per driver (DQ file), vehicle and trailer, stored as `DQ file #id` / `Vehicle #id` / `Trailer #id` and shown as "DQ file" / "Vehicle documents" / "Trailer documents". Staff-created drivers get it at create, public applicants at hire; vehicles and trailers at create and as an Asset Transfer target; `folders:ensure-system` covers older rows (retired ones too). No standard sub-folders yet (ask the client). Custom folders nest up to 5 levels, take the parent's binding, and have unique sibling names ignoring case; no slashes. Deleting: only an empty folder, soft-deleted; `parent_key` turns NULL on delete so the name is free again (08 §8). Uploads: pdf, jpg, jpeg, png, webp, doc, docx, xls, xlsx, csv, txt; 20 MB each, 10 per request; HEIC refused with a message; area `documents`; title only for a single file; the document type must be of the folder's module and on for the company. Documents move only between folders of the same binding; no folder move. A bound folder also needs the entity's view permission. Downloads of DQ-file documents are audited; deletes are soft and audited, the file is kept. The company Documents page shows company-level folders only. Asset Transfer copies the asset's folder tree and live documents. Columns for Phase 2–4 subjects exist now without FKs. | `FolderService`, `DocumentService`, `FolderBrowser`, `FolderController`, `DocumentController`, `documents/partials/browser` |
| P1-5 (2.4) | Photo to PDF (23 Oct 2026, defaults accepted): a JPG, PNG or WebP uploaded into a folder is queued (`ConvertImageToPdf`, `default` queue, after commit) and becomes a one-page Letter PDF: EXIF orientation applied, longest side capped at 3000 px, images over 50 MP not converted, page turned to the aspect ratio, 0.5 in margins. The document's `file_id` becomes the PDF and `original_file_id` the image; the PDF's `converted_from_file_id` is the image (integrity query 13). Both files count toward storage. The original is not deletable on its own. No "Converting" state: the image shows until the PDF replaces it. An unreadable image or a full storage quota fails at once, other errors retry twice; on final failure the document keeps the image and a warning is logged. The title opens the file inline; "Original image" downloads the image; both are audited for DQ files like downloads. Typed compliance uploads are not converted (Q8). Platform switch `documents.convert_images` (`DOCUMENTS_CONVERT_IMAGES`, default on). A future retention purge must remove the PDF before its image (RESTRICT). | `ConvertImageToPdf`, `ImageToPdfConverter`, `DocumentService::attachConvertedPdf`, `StoredFileService::storeGenerated`, `config/documents.php` |
| P1-6 (2.5) | ZIP export per driver and per company (23 Oct 2026, defaults accepted): `exports.create` + feature `zip_export` request an export; a queued or running export of the same scope is returned instead of a new one. `BuildExport` runs on its own `exports` queue connection (`retry_after` 960 s, job timeout 900 s), one build at a time per company through `WithoutOverlapping` (not `ShouldBeUnique`, which would drop a second scope's job), and also for a read-only company (Q2). Contents (Q8): typed compliance documents, application files (licence, medical card, forfeiture), photo, and folder documents with their original image, per driver (terminated included, draft applications and soft-deleted rows excluded), vehicle (plus service-log documents) and trailer, and the company-level folders; layout `Drivers/<Last, First> (#id)/…`, `Vehicles/<unit> (#id)/…`, `Trailers/…`, `Company folders/…`; names sanitised, repeats suffixed " (2)"; one stored file once per entity. A file missing from storage is listed in `Missing files.txt` instead of failing the export. Cap 2 GB of source files (`config/exports.php`), above it the export fails with a message. Export ZIPs are outside the storage limit and counter (integrity query 6 skips them). Ready for 7 days; the requester gets a database + mail notification on ready and on failure. Anyone in the company with `exports.download` may download; every request, download, failure and expiry is audited. The download is an authorized streaming route (local disk); S3 temporary URLs come with the S3 move. `exports:prune` (daily) expires old ZIPs and fails exports queued or running for more than 2 h. Requesting or downloading a ZIP also needs `drivers.view`, and `folders.view` when the company has folders (the Starter plan has none), so a ZIP never holds what the user could not open one by one (23 Oct 2026). | `ExportService`, `BuildExport`, `ExportContents`, `ZipWriter`, `PruneExports`, `config/exports.php` |
| P1-7 | Phase 1 acceptance (23 Oct 2026, defaults accepted): the "perf group" is the `scale` group (`ExportScaleTest`, `PermissionScaleTest`); smoke checks sit in the existing files with a "(smoke)" suffix (social sign-up to dashboard and back; a granted permission opens a route and its revoke closes it); 02, 13 and the two feature documents get status changes for Phase 1 only. The Phase 0 statuses there were never updated, and the stale P1 details in `Technical-Features-and-Workflow.md` (`exports:expire`, `ShouldBeUnique`, signed download) are left as they are: 01–14 win. Both are follow-ups. | `SocialLoginTest`, `UserPermissionGrantTest`, docs |
| Co-driver | No co-driver on asset groups (23 Oct 2026). Remove `asset_groups.co_driver_id` from the design. | [08](08-database-schema.md) §9 |
| C7 | Nothing is missing from the client's list (23 Oct 2026). Closed. | — |
| Platform 2FA | Not needed (23 Oct 2026). Removed from the go-live list; 04 §2 stays a recommendation only. | [04](04-platform-administration.md) §2 |
| Stripe Price | Entered by hand as `stripe_price_id` (23 Oct 2026). | `/platform/plans` |
| Support and platform defaults | Confirmed (23 Oct 2026): the support-session defaults and the platform defaults of WP8a/WP8b are final. | [04](04-platform-administration.md) |
| Emergency support access | Not needed (23 Oct 2026). Q1 four-eyes stays the only path. | [04](04-platform-administration.md) §3–4 |
| Scheduler | Runs every minute in production (23 Oct 2026). Octane is not planned; the design stays Octane-safe. | — |
| Standard sub-folders | None (23 Oct 2026). | P1-4 |
| X1 | Expense categories are a per-company CRUD list; the defaults (toll, lumper, scale, parking, repair, other) are seeded and stay. Deleting a category in use is refused or archived. Design **PENDING** (Phase 4). | [08](08-database-schema.md) §11 |
| Z1 | Registration asks for the company time zone; `America/Chicago` is preselected. Done: the password and social sign-up forms require one of the North American zones in `App\Support\TimeZones`; the company settings page (`company.edit`) and the platform company form edit it. | `RegisteredUserController`, `FinishSocialSignupRequest`, `CompanyController`, `CompanyProfileRequest`, `TimeZones` |
| Q17 | QuickBooks Payroll API is deferred; v1 keeps the payroll summary export. | Section 5 |

## 3. Still open

| ID | Question | Current handling |
|---|---|---|
| Retention periods [L] | See section 4 | 23 Oct 2026: keep the defaults in [08](08-database-schema.md) §15; the purge job below uses them. Counsel can change them later. |
| WP9 hand-off | The `stored_files` write path, the wizard's medical-card upload creating the `medical_examiner_certificate` document (F17), and `PolicyPdf::resolveFor()` falling back to the `site_settings` platform defaults. | Approved 23 Oct 2026: WP9 owns them. |
| Site-settings fields | 04 §8 identity, theme, SEO and mail-sender columns have no UI. | Done 23 Oct 2026: Platform > Site settings edits the share image, card type, canonical base URL, site-wide noindex switch, Search Console value, default time zone (preselected at sign-up), sender name and email logo, and each is read where it takes effect (`Seo`, `SeoController`, `x-seo`, `TimeZones::preselected`, `MailIdentity`, `Theme::emailLogo`). The sender address stays in `.env` (SPF/DKIM). Footer text and legal links moved to the footer editor earlier. |
| Transfer back to the source | A transferred asset stays soft-deleted at the source, and `(company_id, unit_no)` / `(company_id, vin)` include trashed rows, so moving it back is refused. | Decided 23 Oct 2026: build a way to move it back. Design **PENDING**. |
| Company purge | `platform.companies.purge` exists but the purge job is not built. | Decided 23 Oct 2026: finish the job with the default retention periods. Design **PENDING**. |
| Docs follow-ups (P1-7) | Phase 0 statuses were never updated in 02, `DriverFilesHub-Features.md` and `Technical-Features-and-Workflow.md`. The technical file's ZIP row and schedule table still say `ShouldBeUnique`, a signed download and `exports:expire`; the code uses `WithoutOverlapping`, an authorized streaming route and `exports:prune`. `docs/erd.md` still draws the 15 Oct `site_settings`, `companies`, `plans` and `roles` columns and not the public-site tables. | Done 23 Oct 2026 (docs pass): 02, 04, 13, README, both feature documents and `docs/erd.md` (re-read from the MySQL schema). |
| Phase 0 gaps (docs pass, 23 Oct 2026) | Two Phase 0 items were never built: a driver Terminate action that ends employment and switches off the driver's login (the `terminated` status exists), and closing the public application link when the plan lacks `driver_applications` (the feature key is not checked anywhere). | **PENDING**: proposed for Phase 2 (P2-1). |
| MySQL 8.0.16 run | WP1 was verified on SQLite 3.45 and MySQL 9.3 only (no 8.0 server available locally). | Run `migrate:fresh --seed`, `integrity:check` and the Schema/Integrity tests on 8.0.16 before go-live. **PENDING** |
| Google Maps Platform | Terms and cost for the map picker [L]. | Client wants free use. **PENDING**: see section 4. |

### Before Phase 2

| Item | What it needs |
|---|---|
| Open questions | Co-driver and C7 are closed (section 2). Open: Google Maps Platform terms and cost for the map picker [L] (§4). |
| Site-settings UI | Done 23 Oct 2026. |
| Docs pass | Done 23 Oct 2026. |
| Registration time zone | Z1, section 2. |
| Transfer back and company purge | Built before Phase 2 (section 3). |
| Trip folders on P1-4 | `folders.trip_id` and the `documents` subject columns `trip_id`, `trip_event_id`, `fuel_purchase_id`, `inspection_id` exist and are in the CHECKs, without FKs: add them in a MySQL-only migration. `Folder::BINDINGS` already maps `trip_id` to `trip`; let `FolderService::ensureSystemFolder` take a Trip, create the trip folder when a trip is created, extend `folders:ensure-system`, and cover trips in integrity query 5 (subject vs folder). |
| ZIP per trip on P1-6 | The `exports.type` enum already has `zip_trip`. Add the trip permission check to `ExportService::canAccess` / `canRequest`, a trip section in `ExportContents` (trip folder, event and fuel documents), the `trip_id` scope parameter, the route, and its `IsolationMatrixTest` fixture. |

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
| App Store rule 4.8 (Sign in with Apple), Facebook Login app review, Google Maps Platform terms | Phase 1 and Phase 3. Web sign-in (P1-3) needs, before the switches are turned on: Google OAuth consent-screen verification, Facebook Login app review, and a Facebook data-deletion instructions URL (a "Delete your data" section on the privacy-policy page, edited in Platform > Content; no callback endpoint). |

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
| Default company time zone (`America/Chicago`) | Decided 23 Oct 2026: ask for it during registration (see section 2, Z1); `America/Chicago` stays the preselected value |
| Intuit Payroll API access terms | Not needed: payroll API work is deferred (Q17) |
| Expense categories | Decided 23 Oct 2026: a per-company CRUD list seeded with toll, lumper, scale, parking, repair, other (see section 2, X1) |

# 08 · Database schema (target)

- **What:** every table in the target design, with its purpose, ownership, key columns, foreign keys and delete rules, unique constraints and indexes.
- **Diagrams:** [../erd-proposed.md](../erd-proposed.md).
- **Why the existing tables change:** [09-erd-audit.md](09-erd-audit.md).

## 1. Conventions

| Topic | Rule |
|---|---|
| Keys | `id` bigint unsigned auto-increment. Exceptions: `features` (string key), framework tables, and pure pivots with composite primary keys. |
| **cFK** | Composite tenant FK: `(company_id, x_id)` → `x(company_id, id)`. Every tenant parent has `UNIQUE(company_id, id)`. MySQL only checks a composite FK when every column is non-null, so a null optional link is allowed. |
| Timestamps | `created_at`, `updated_at` unless noted |
| Soft deletes | Only where restore or audit history is needed: drivers, vehicles, trailers, asset groups, service logs, schedules, compliance documents, documents, folders, trips, shippers, expenses, messages. Lookups use `is_active` or the existing soft delete (`document_types`). |
| Money | `decimal(12,2)` plus `currency char(3)`. Unit prices for fuel and rates use `decimal(8,3)` / `decimal(10,4)`. |
| Coordinates | `latitude decimal(10,7)`, `longitude decimal(10,7)` |
| Places | `state_code char(2)` and `country_code char(2)`, validated against `states` / `countries` (US and Canada, PO-11). Free-text state and country fields are removed. |
| Booleans | `boolean` in new and rebuilt tables. The `enum('yes','no')` columns in application tables become booleans during the rebuild. |
| Generated columns | Written as `CASE WHEN … THEN … END`, which works in both MySQL and SQLite (the test database). Used to enforce "at most one open or current row". |
| CHECK constraints | Production runs MySQL 8.0.16 or later (decided D7a). The permission spike ran on 9.3. MySQL forbids `CASCADE` / `SET NULL` on FK columns used in a CHECK, so those FKs use `RESTRICT`. Laravel has no `Blueprint::check()`, so migrations call `App\Support\CheckConstraints::add()` as their last statement: `ALTER TABLE … ADD CONSTRAINT` on MySQL, an empty-table recreate on SQLite [V, WP1]. |
| SQLite alters | [V, WP1] Any later `Schema::table()` alter on SQLite rebuilds the table from Laravel's state and silently drops raw CHECKs **and enum CHECKs**. So no migration alters a table after its create. The companies owner FK is declared inside `create_companies_table` on SQLite (forward references are allowed there) and added by `add_owner_fk_to_companies` on MySQL only. |
| Generated-column base columns | [V on SQLite 3.45 and MySQL 9.3, WP1; 8.0.16 still PENDING] Stored `CASE WHEN` columns with unique indexes enforce "one current/open/active" on both engines. To verify on 8.0.16: MySQL limits referential actions on the base columns of indexed generated columns. Such base columns therefore use `RESTRICT` FKs (`licenses.driver_id`, `asset_groups.vehicle_id`, `trips.driver_id`, …). |
| Tenant mark | Each table below is labelled **Platform**, **Tenant root**, **Tenant child (company_id)**, **Tenant detail (via parent)**, **Reference** or **Framework** ([03](03-multi-tenant-architecture.md) §2) |

## 2. Delete rules by class

| Class | ON DELETE | Notes |
|---|---|---|
| Tenant root → `companies` | RESTRICT | Companies are deactivated. The purge job deletes children in dependency order after the retention period, then the company. |
| Detail → parent in the same aggregate | CASCADE | Unless the column is in a CHECK or is the base of an indexed generated column. In that case RESTRICT, and the purge job deletes explicitly. |
| Cross-aggregate references | RESTRICT | Parents are soft-deleted in normal use |
| Actor pointers (`*_by_user_id`, `actor_user_id`) | SET NULL (single-column FK) | Never composite, because SET NULL on a composite would null `company_id`. Same-company membership is validated in the app. |
| Financial | RESTRICT | Payments, subscriptions and prices are never cascade-deleted |
| Lookups (LKP-01) | RESTRICT | Already true today [V] |

---

## 3. Platform tables

### `platform_users` (Platform)

Platform staff accounts, behind the `platform` guard ([04](04-platform-administration.md)).

- **Columns:** `id`, `name`, `email`, `password`, `status` enum(active, inactive), `two_factor_secret` (encrypted text, null), `two_factor_recovery_codes` (encrypted text, null), `two_factor_confirmed_at`, `last_login_at`, `remember_token`, timestamps.
- **Unique:** `email`.
- **Email rule (D3a):** an email may never exist in both `platform_users` and `users`. `EmailIdentityService` checks both tables under a named lock, and the nightly integrity query looks for overlaps ([04](04-platform-administration.md) §2).

### `platform_password_reset_tokens` (Platform)

- **Columns:** `email` (PK), `token`, `created_at`.

### `site_settings` (Platform, existing, modified)

The single-row platform configuration [V].

- **Existing columns [V]** (`…084729_create_site_settings_table.php`):
  - Platform info: `site_name`, `email`, `phone`, `address`;
  - Branding: `logo`, `favicon`;
  - Social: `facebook_url`, `twitter_url`, `linkedin_url`;
  - SEO: `meta_title`, `meta_description`, `meta_keywords`;
  - Analytics: `google_analytics_id`;
  - Live chat: `tawk_enabled`, `tawk_property_id`, `tawk_widget_id`, `tawk_widget_code`.
- **Added columns:**
  - `default_alcohol_drug_test_policy_pdf`, `default_general_work_policy_pdf` (paths; these replace the `policy_pdfs` row with a null `company_id`);
  - `trial_plan_id` (FK `plans`, RESTRICT, null), `trial_days` (default 14), `grace_days` (default 7, decided Q2), `support_session_max_minutes` (default 240).
- **Added columns, platform identity [R]:**
  - `legal_name`, `support_email`, `support_phone`, `footer_text`, `terms_url`, `privacy_url`, `timezone` (default `America/Chicago` [A]).
- **Added columns, theme [R]:**
  - `primary_color`, `accent_color` (hex), `default_color_mode` enum(light, dark, system), `logo_dark`, `email_logo`.
  - How the colors reach Tailwind is PENDING (the current Tailwind setup has not been checked).
- **Added columns, SEO [R]:**
  - `og_image`, `twitter_card` (default `summary_large_image`), `canonical_base_url`, `robots_noindex_default` (bool), `search_console_verification`.
  - Public pages are indexable and private pages are `noindex` (skill `seo-checklist`). `robots_noindex_default` only covers pages that set no explicit rule.
- **Added columns, mail sender [R]:** `mail_from_name`, `mail_from_address`. Whether these replace the `.env` values is PENDING.
- **Not in `site_settings`:** per-company branding (`companies.logo_path`), sitemap/robots toggles (route-level, [A]) and SMS sender (PENDING, no decision yet).
- **Migration:** the columns are added by editing the existing consolidated create-table file, not by a new alter migration (decided).

### `features` (Platform)

The feature registry, synced from the `App\Features\Feature` enum ([05](05-plans-features-entitlements.md) §2).

- **PK:** `key` varchar(64).
- **Columns:** `name`, `group`, `kind` enum(module, limit), `unit` (null), `availability` enum(general, override_only, disabled), `sort_order`, timestamps.

### `plans` (Platform, existing, modified)

The product. Prices and features move out.

- **Columns:** `id`, `name`, `slug`, `description`, `is_public`, `is_active`, `sort_order`, `marketing_bullets` (json, display only), timestamps.
- **Removed:** `price`, `currency`, `billing_cycle`, `duration_days`, `trial_days`, `stripe_plan_id`, `stripe_price_id`, `features`.
- **Unique:** `slug`.

### `plan_prices` (Platform)

Immutable price rows ([07](07-subscription-billing.md) §3).

- **Columns:** `id`, `plan_id`, `interval` enum(month, year), `amount` decimal(10,2), `currency` char(3), `stripe_price_id`, `archived_at`, `current_flag` (generated: `CASE WHEN archived_at IS NULL THEN 1 END`), timestamps.
- **FK:** `plan_id` → `plans`, RESTRICT.
- **Unique:** `stripe_price_id`; `(plan_id, interval, currency, current_flag)`.

### `plan_features` (Platform)

What each plan includes.

- **PK:** `(plan_id, feature_key)`.
- **Columns:** `enabled` bool, `limit_value` int unsigned (null means unlimited), timestamps.
- **FKs:**
  - `plan_id` → `plans`, CASCADE (only reachable for unused plans; subscribed plans are RESTRICT);
  - `feature_key` → `features.key`, RESTRICT.

### `company_feature_overrides` (Platform-owned, about a company)

Append-only exception history ([05](05-plans-features-entitlements.md) §6).

- **Columns:**
  - `id`, `company_id`, `feature_key`;
  - `enabled` (bool, null), `limit_value` (int unsigned, null), `is_unlimited` bool;
  - `reason`, `starts_at`, `ends_at` (null);
  - `granted_by_platform_user_id`;
  - `revoked_at`, `revoked_by_platform_user_id`, `revoke_reason`;
  - `open_flag` (generated: `CASE WHEN revoked_at IS NULL THEN 1 END`), timestamps.
- **FKs:**
  - `company_id` → `companies`, RESTRICT;
  - `feature_key` → `features`, RESTRICT;
  - the platform-user columns → `platform_users`, SET NULL.
- **Unique:** `(company_id, feature_key, open_flag)`.
- **CHECK:**
  - `NOT (is_unlimited AND limit_value IS NOT NULL)`;
  - `ends_at IS NULL OR ends_at > starts_at`.

### `stripe_events` (Platform)

Webhook inbox ([07](07-subscription-billing.md) §6.2).

- **Columns:** `id`, `event_id`, `type`, `stripe_created_at`, `payload` json, `status` enum(pending, processed, ignored, failed), `error`, `received_at`, `processed_at`.
- **Unique:** `event_id`.
- **Index:** `(status, received_at)`.

### `asset_transfers` (Platform)

The audited platform operation that moves a vehicle or trailer between companies (PO-08, D11). See [04](04-platform-administration.md) §7.

- **Columns:**
  - `id`, `asset_type` enum(vehicle, trailer);
  - `source_company_id`, `target_company_id`;
  - `source_vehicle_id`, `target_vehicle_id`, `source_trailer_id`, `target_trailer_id` (all null; which pair is set follows `asset_type`);
  - `reason`, `status` enum(completed, failed);
  - `copied_counts` json (documents, service logs, schedules), `bytes_copied`;
  - `performed_by_platform_user_id`, `performed_at`, timestamps.
- **FKs:**
  - `source_company_id`, `target_company_id` → `companies`, RESTRICT;
  - `(source_company_id, source_vehicle_id)` and `(target_company_id, target_vehicle_id)` → `vehicles(company_id, id)`, RESTRICT;
  - the same for trailers;
  - `performed_by_platform_user_id` → `platform_users`, RESTRICT.
- **CHECK:**
  - `source_company_id <> target_company_id`;
  - `(asset_type = 'vehicle' AND source_vehicle_id IS NOT NULL AND source_trailer_id IS NULL AND target_trailer_id IS NULL) OR (asset_type = 'trailer' AND source_trailer_id IS NOT NULL AND source_vehicle_id IS NULL AND target_vehicle_id IS NULL)`.
- **Indexes:** `(source_company_id, performed_at)`, `(target_company_id, performed_at)`.
- **The source asset** is soft-deleted and keeps its history (read-only). This table records where it went.

### `platform_support_sessions` and `audit_logs`

See section 10.

---

## 4. Accounts and tenancy

### `companies` (Tenant root, existing, modified)

- **Columns:**
  - Identity: `id`, `owner_user_id` (null), `company_name`, `slug` (NOT NULL), `dot_number` (null) [R], `mc_number` (null) [R].
  - Contact: `email` (company contact, no longer unique and no longer synced from the owner's login, decided D3b), `billing_email`, `phone`, `fax`, `address`, `city`, `state_code`, `zip`, `timezone` (default `America/Chicago` [A]), `logo_path`.
  - Status: `status` enum(active, suspended, deactivated), `status_changed_at`, `status_reason`.
  - Billing and limits: `stripe_customer_id` (null), `trial_used_at`, `entitlements_version` int unsigned (default 1), `storage_bytes` bigint unsigned (default 0).
  - timestamps.
- **FK:** `(id, owner_user_id)` → `users(company_id, id)`, RESTRICT. The owner must belong to the company. This replaces `user_id` and its CASCADE [V].
- **Unique:** `slug`, `stripe_customer_id`.
- **Index:** `status`.
- **Creation order** (one transaction): insert the company with no owner → insert the user → set `owner_user_id`. [A] On SQLite, adding this FK after both tables exist relies on Laravel's table rebuild. Verify in the Phase 0 spike, or fall back to an app check plus an integrity test.

### `company_settings` (Tenant root, 1:1)

- **PK:** `company_id` (FK → `companies`, CASCADE).
- **Columns:**
  - `trip_number_prefix`, `distance_unit` enum(mi, km), `weight_unit` enum(lb, kg), `currency` char(3);
  - `week_starts_on` tinyint (0 = Sunday; defines the workweek);
  - overtime (C3, daily thresholds):
    - `overtime_daily_after_minutes` smallint unsigned (default 480);
    - `double_overtime_daily_after_minutes` smallint unsigned (null; default 720);
    - `overtime_weekly_after_minutes` smallint unsigned (null; off by default);
    - `overtime_multiplier` decimal(4,2) (default 1.50), `double_overtime_multiplier` decimal(4,2) (default 2.00);
  - timestamps.
- **CHECK:**
  - `double_overtime_daily_after_minutes IS NULL OR double_overtime_daily_after_minutes > overtime_daily_after_minutes`;
  - `overtime_multiplier >= 1 AND double_overtime_multiplier >= overtime_multiplier`.

### `users` (Tenant root, existing, modified)

- **Columns:** `id`, `company_id` (NOT NULL), `user_type` enum(member, support), `name`, `email`, `email_verified_at`, `password`, `phone`, `status` enum(active, inactive), `last_login_at`, `remember_token`, timestamps.
- **Removed:** `stripe_id`, `pm_type`, `pm_last_four`, `trial_ends_at`.
- **FK:** `company_id` → `companies`, RESTRICT.
- **Unique:** `email` (global, stored lowercase; D3); `(company_id, id)`.
- **Index:** `(company_id, status)`.
- **Not tenant-scoped,** so login and password reset work. Platform staff are **not** in this table.

### `company_invitations` (Tenant root)

- **Columns:**
  - `id`, `company_id`, `email`, `role_id`;
  - `driver_id` (null; for driver-login invites);
  - `token_hash` char(64);
  - `invited_by_user_id`, `expires_at`, `accepted_at`, `accepted_user_id`, `revoked_at`, timestamps.
- **FKs:**
  - `(company_id, role_id)` → `roles(team_id, id)`, CASCADE;
  - cFK `driver_id`, RESTRICT;
  - `invited_by_user_id` and `accepted_user_id` → `users`, SET NULL.
- **Unique:** `token_hash`.
- **Index:** `(company_id, email)`.

### `social_accounts` (Tenant detail, via user)

- **Columns:** `id`, `user_id`, `provider` enum(google, facebook, apple), `provider_user_id`, `provider_email`, timestamps.
- **FK:** `user_id` → `users`, CASCADE.
- **Unique:** `(provider, provider_user_id)`; `(user_id, provider)`.

### `device_tokens` (Tenant root)

Push targets.

- **Columns:** `id`, `company_id`, `user_id`, `platform` enum(ios, android), `token`, `last_seen_at`, timestamps.
- **FK:** cFK `user_id` → `users(company_id, id)`, CASCADE.
- **Unique:** `token`.

### Framework tables, unchanged

- `sessions`: `user_id` refers to the web guard only.
- `password_reset_tokens`.
- `personal_access_tokens` (Sanctum, new; `tokenable` morph, which is always `App\Models\User`).

---

## 5. Authorization (Spatie, teams)

See [06-roles-permissions.md](06-roles-permissions.md) §4 for the full rules. Permission checks are database-first (§2 there). In short:

| Table | Columns added | Key rules |
|---|---|---|
| `permissions` | `feature_key`, `is_write`, `self_scoped`, `description` | Spatie's unique key on `(name, guard_name)` |
| `roles` | `team_id` (company id, or 0 for platform), `system_key`, `is_system`, `description` | UK `(team_id, name, guard_name)`, `(team_id, system_key)`, `(team_id, id)` |
| `model_has_roles` | — | Composite FK `(team_id, role_id)` → `roles(team_id, id)`, CASCADE |
| `model_has_permissions`, `role_has_permissions` | — | As Spatie, plus a `(model_type, model_id, team_id)` index on `model_has_permissions` and a `role_id` index on `role_has_permissions` for the resolver |

---

## 6. Drivers and the DQ file

### `drivers` (Tenant root, existing, modified)

**Columns:**
- Ownership and login: `id`, `company_id`, `created_by_user_id`, `user_id` (the driver's login, null).
- Name and identity: `first_name`, `middle_name`, `last_name`, `suffix`, `date_of_birth`, `ssn` (encrypted cast).
- Contact: `main_phone`, `alt_phone`, `email`, `photo_file_id`.
- Business: `business_name`, `employer_identification_number`, `federal_tax_classification`.
- Address: `address`, `city`, `state_code`, `country_code`, `postal_code`.
- Flags: `twic_card`, `passport`, `hazmat` bool, `lcv_certificate` bool.
- Pay classification (Phase 4):
  - `worker_type` enum(employee, contractor), default employee. Contractors (who file W-9 data, the existing business, EIN and tax-classification fields [V]) are excluded from payroll and overtime.
  - `overtime_exempt` bool, default false.
  - `overtime_exempt_basis` enum(motor_carrier_act, other) (null).

  [L] The company decides whether a driver is overtime-exempt; the software only applies the flag (see [14](14-open-questions.md) §5).
- Status and history:
  - `application_status` enum(draft, pending, submitted, under_review, approved, rejected, withdrawn) (null for drivers created directly by staff);
  - `employment_status` enum(applicant, active, inactive, terminated);
  - `source` enum(admin, public_application);
  - `withdrawn_at`, `rejection_reason`, `rejection_notes`, `rejected_at`;
  - `hired_at` (when it was recorded), `hire_date` (employment start);
  - `terminated_at`, `termination_date`;
  - `decided_by_user_id` (was `action_by`).
- timestamps, `deleted_at`.

**Status split [R].** Today's single `status` mixes the application and employment lifecycles [V]. The mapping is in [09-erd-audit.md](09-erd-audit.md) F15. Verify against `DriverHireService` transitions in Phase 0.

**Removed:**
- `rejection_date`, which duplicates `rejected_at`;
- `medical_certificate_expiration_date`, now a compliance document (F17);
- the old `user_id`, renamed `created_by_user_id`.

**FKs:**
- `company_id` → `companies`, RESTRICT;
- `created_by_user_id` and `decided_by_user_id` → `users`, SET NULL;
- cFK `user_id` → `users(company_id, id)`, RESTRICT;
- cFK `photo_file_id` → `stored_files`, RESTRICT.

**Unique:**
- `(company_id, email)` (APP-07 [V]);
- `(company_id, id)`;
- `user_id`, so one login maps to at most one driver.

**Indexes:**
- `(company_id, employment_status)`;
- `(company_id, application_status)`;
- `(company_id, main_phone)` for the public-application lookups;
- `(company_id, last_name, first_name)` for list sorting.

### Driver application detail tables (Tenant detail, via driver)

All belong to one driver and are only read through it.

| Table | Changes | FK `driver_id` | Notes |
|---|---|---|---|
| `residence_addresses` | `state` / `country` become `state_code` / `country_code` | CASCADE | — |
| `licenses` | Drop `repeat_license_number`. Add `is_current` bool and a generated `current_driver_id = CASE WHEN is_current THEN driver_id END` (unique). Codes for state and country. | **RESTRICT** (base of a generated column) | `first_name`, `last_name` kept as the name printed on the licence (snapshot) |
| `experiences` | `approx_miles` becomes int unsigned. `experience` becomes bool. | CASCADE | — |
| `accidents` | Injuries and fatalities become smallint unsigned. The "no accident" row (`accident = 'no'`) is replaced by `driver_documents.has_accidents`. | CASCADE | Rows only for real events |
| `violations` | Drop the per-row `violation_record_signature` / `_date_signed` (kept in `driver_documents`). The "no violation" row is replaced by `driver_documents.has_violations`. | CASCADE | `vehicle_type` stays as a text snapshot |
| `forfeitures` | yes/no enums become booleans | CASCADE | — |
| `employment_records` | Codes for state and country | CASCADE | `employer_*` columns are legitimate snapshots of external employers |
| `driver_documents` | `driver_id` becomes **unique**. `license_front`, `license_back`, `medical_card`, `forfeiture_document` become `*_file_id` (single FK → `stored_files`, RESTRICT). Add `has_accidents`, `has_violations`. `document_path`: [A] remove if unused. | CASCADE | The application attestation record: signatures, consents, policy acknowledgements |

### Compliance documents (Tenant child, existing, modified)

Applies to `driver_compliance_documents`, `vehicle_documents` and `trailer_documents`.

- **Columns:**
  - `id`, `company_id`, the owner id (`driver_id` / `vehicle_id` / `trailer_id`), `document_type_id`, `file_id`;
  - `file_date` (issue date; added to vehicle and trailer documents), `expiry_date`;
  - `description`, `uploaded_by_user_id`;
  - timestamps, `deleted_at` (soft deletes on all three, with the model trait added; F11).
- **Removed:** `file_path`. The path now lives in `stored_files`.
- **FKs:**
  - cFK owner → `drivers` / `vehicles` / `trailers`, CASCADE (aggregate detail);
  - cFK `file_id` → `stored_files`, RESTRICT;
  - `document_type_id` → `document_types`, RESTRICT [V];
  - `uploaded_by_user_id`, SET NULL.
- **Indexes:**
  - `(owner_id, document_type_id, expiry_date)`, used by the compliance subqueries;
  - `(company_id, expiry_date)`, used by summaries and the digest.

### `company_document_type` (Tenant child, existing)

The opt-out list (LKP-01) [V]. Unchanged.

### `document_types` (Reference, existing, modified)

- **Added columns:** `system_key` (null, unique; e.g. `medical_examiner_certificate`), `requires_expiry` bool.
- **Unique:** `(module, name)`.
- The model gains `SoftDeletes` to match the existing `deleted_at` column.

### `policy_pdfs` (Tenant root, existing, modified)

- **Columns:** `id`, `company_id` (**NOT NULL**, unique), `alcohol_drug_test_policy_file_id`, `general_work_policy_file_id`, timestamps.
- **FKs:** cFK → `stored_files`, RESTRICT; `company_id` → `companies`, RESTRICT.
- **Serving:** files are on the private disk and served to applicants through a slug-scoped route. The platform defaults live in `site_settings` (PO-14 fallback kept).

---

## 7. Fleet and maintenance

### `vehicles` (Tenant root, existing, modified)

- **Added:** `plate_number`, `plate_state_code`, `photo_file_id` (cFK → `stored_files`, RESTRICT).
- **FKs:** `company_id` RESTRICT (was CASCADE); lookups RESTRICT [V].
- **Unique:** `(company_id, unit_no)`, `(company_id, vin)` [V], `(company_id, id)`.
- **Dropped indexes:** the single-column `unit_no` and `vin` indexes. Search is tenant-scoped with leading wildcards, so they are never used.

### `trailers` (Tenant root, existing, modified)

- Same changes as `vehicles`.
- `equipment_types_id` is renamed `equipment_type_id`.

### `asset_groups` (Tenant root, existing, modified)

The default pairing of a driver with equipment.

**Columns:**
- `id`, `company_id`, `group_name`.
- `driver_id`, `co_driver_id` (null; still PENDING, see [14](14-open-questions.md) §3).
- `vehicle_id`, `trailer_id` (null).
- `status` enum(active, inactive).
- `active_vehicle_id` (generated: `CASE WHEN status='active' AND deleted_at IS NULL THEN vehicle_id END`).
- `active_trailer_id` (generated, the same way).
- timestamps, `deleted_at`.

**Removed:** `primary_driver_name`, `primary_driver_phone`, `primary_driver_email`, `second_driver_name`, `second_driver_phone`, `second_driver_email` (F1–F3).

**FKs:** cFK on driver, co-driver, vehicle and trailer, all RESTRICT. They were CASCADE [V]; RESTRICT is needed here because these are the base columns of generated columns.

**Unique:**
- `(company_id, group_name)` (enforced only in the app today [V]);
- `active_vehicle_id`;
- `active_trailer_id`;
- `(company_id, id)`.

**CHECK:** `co_driver_id IS NULL OR co_driver_id <> driver_id`.

### Lookups (Reference, existing)

`vehicle_types`, `fuel_types`, `equipment_types`, `vehicle_groups`, `maintenance_categories`:
- add `is_active` bool;
- unique `name`;
- writes are platform-only (LKP-01).

### `service_logs` (Tenant root, existing, modified)

- **Added:** `maintenance_schedule_id` (null). This fixes the broken `MaintenanceSchedule::serviceLogs()` relation [V].
- **FKs:**
  - `company_id`, RESTRICT;
  - cFK `vehicle_id`, RESTRICT (was a plain CASCADE);
  - cFK `maintenance_schedule_id`, RESTRICT.
- **Indexes:** `(company_id, service_date)`, `(vehicle_id, service_date)`. The redundant single `vehicle_id` index is dropped.

### `service_log_category` (Tenant detail)

Unchanged, except the redundant `service_log_id` index is dropped [V].

### `service_documents` (Tenant child, existing, modified)

- **Columns:** `id`, `company_id`, `service_log_id`, `file_id`, timestamps.
- **Moved to `stored_files`:** `original_name`, `file_name`, `file_path`, `mime_type`, `file_size`.
- **FKs:** cFK `service_log_id`, CASCADE; cFK `file_id`, RESTRICT.

### `maintenance_schedules` (Tenant root, existing, modified)

- **Removed:** `is_active`, which duplicates `status` (F14).
- **FKs:**
  - cFK `vehicle_id`, RESTRICT (was CASCADE);
  - `maintenance_category_id`, RESTRICT.
- **Index:** `(company_id, status, next_due_date)`.
- **Rename [R]:** `last_due_*` becomes `last_service_*`, because it stores the last completed service.

---

## 8. Files, folders and documents

### `stored_files` (Tenant root)

One row per stored object.

- **Columns:**
  - `id`, `company_id`, `disk`;
  - `path` (`companies/{company_id}/{area}/{yyyy}/{mm}/{hash}.{ext}`);
  - `original_name`, `mime_type`, `size_bytes`, `checksum_sha256`;
  - `converted_from_file_id` (null; the source image when a PDF was generated);
  - `uploaded_by_user_id`, `created_at`.
- **FKs:**
  - `company_id`, RESTRICT;
  - cFK `converted_from_file_id` → `stored_files`, RESTRICT;
  - `uploaded_by_user_id`, SET NULL.
- **Unique:** `path`, `(company_id, id)`.
- **Index:** `(company_id, created_at)`.
- **Storage counter:** `companies.storage_bytes` changes in the same transaction ([05](05-plans-features-entitlements.md) §5).

### `folders` (Tenant root)

**Columns:**
- `id`, `company_id`, `parent_id` (null), `name`;
- `system_key` (null; set on the standard folders);
- the entity binding: `driver_id`, `vehicle_id`, `trailer_id`, `trip_id` (all null);
- `parent_key` (generated: `CASE WHEN parent_id IS NULL THEN 0 ELSE parent_id END`);
- `created_by_user_id`, timestamps, `deleted_at`.

**FKs:**
- cFK `parent_id` → `folders`, RESTRICT;
- cFK on each binding column, RESTRICT (they are used in a CHECK);
- `created_by_user_id`, SET NULL.

**Unique:** `(company_id, parent_key, name)`. The generated `parent_key` is needed because MySQL allows repeated NULLs, which would let two root folders share a name.

**CHECK:** at most one entity binding: `(driver_id IS NOT NULL) + (vehicle_id IS NOT NULL) + (trailer_id IS NOT NULL) + (trip_id IS NOT NULL) <= 1`.

**Rules in the service:**
- A child folder must have the same binding as its parent, or none.
- Maximum depth 5 [R].
- System folders can't be renamed or deleted.

### `documents` (Tenant root)

Free-form documents in folders, plus attachments to trip events, fuel purchases, expenses and inspections.

**Columns:**
- `id`, `company_id`;
- `folder_id` (null), `file_id`, `original_file_id` (null);
- `title`, `document_type_id` (null), `issued_on`, `expires_on`;
- the **subject**, all null: `driver_id`, `vehicle_id`, `trailer_id`, `trip_id`, `trip_event_id`, `fuel_purchase_id`, `expense_id`, `service_log_id`, `inspection_id`;
- `uploaded_by_user_id`, timestamps, `deleted_at`.

**FKs:**
- cFK `folder_id`, RESTRICT;
- cFK `file_id` and `original_file_id`, RESTRICT;
- `document_type_id`, RESTRICT;
- cFK on each subject column, RESTRICT (they are used in a CHECK);
- `uploaded_by_user_id`, SET NULL.

**CHECK:** at most one subject is set. No subject means a company-level document.

**Indexes:** `(company_id, folder_id)`. The composite indexes from each cFK cover subject lookups.

**Ownership versus filing ([09](09-erd-audit.md) F18):**
- The **subject** says what the document is about.
- The **folder** says where it is filed. The two are independent.
- Rule, enforced in the service plus an integrity query: if the folder is bound to an entity, the subject must be that entity or one of its children. For example, an event of trip T may sit in trip T's folder.

**Compliance documents stay in their typed tables** (section 6). Standard folders show them as virtual entries next to the real `documents` rows.

---

## 9. Operations: trips, inspections, events, tracking

### `shippers` (Tenant root)

Saved shippers, reusable on later trips.

- **Columns:** `id`, `company_id`, `name`, `contact_name`, `phone`, `email`, `address_line1`, `address_line2`, `city`, `state_code`, `zip`, `country_code`, `latitude`, `longitude`, `google_place_id`, `notes`, `is_active`, timestamps, `deleted_at`.
- **FK:** `company_id`, RESTRICT.
- **Unique:** `(company_id, id)`.
- **Index:** `(company_id, name)`.

### `trip_number_sequences` (Tenant root, 1:1)

- **PK:** `company_id` (FK, CASCADE).
- **Column:** `next_number` bigint unsigned.
- **How it works:** a trip number is taken with `SELECT … FOR UPDATE` on this row, inside the same transaction as the trip insert.

### `trips` (Tenant root)

**Columns:**
- Numbering: `id`, `company_id`, `trip_number`, `trip_date`, `number_confirmed_at`, `number_confirmed_by_user_id`.
- Status: `status` enum(draft, planned, assigned, in_progress, completed, cancelled).
- Assignment: `driver_id`, `vehicle_id`, `trailer_id`, `shipper_id` (all null).
- Load: `product`, `weight` decimal(10,2), `weight_unit`, `load_pay` decimal(12,2), `currency`.
- Equipment check: `equipment_confirmed_at`, `equipment_confirmed_by_user_id`.
- Distance and progress: `planned_distance_miles`, `actual_distance_miles`, `paid_miles` (defaults to `planned_distance_miles`; editable with `pay.manage` until the trip is on an approved payroll run), `started_at`, `completed_at`, `cancelled_at`, `cancel_reason`.
- Snapshots taken at start: `vehicle_unit_no_snapshot`, `trailer_unit_no_snapshot`.
- One-active guards: `active_driver_id` (generated: `CASE WHEN status='in_progress' THEN driver_id END`), `active_vehicle_id` (generated, the same way).
- Other: `created_by_user_id`, `notes`, timestamps, `deleted_at`.

**FKs:**
- `company_id`, RESTRICT;
- cFK driver, vehicle, trailer, shipper, all RESTRICT;
- the user pointers, SET NULL.

**Unique:**
- `(company_id, trip_number)`;
- `(company_id, id)`;
- `active_driver_id` (one in-progress trip per driver);
- `active_vehicle_id`.

**CHECK:** `status NOT IN ('assigned','in_progress','completed') OR (driver_id IS NOT NULL AND vehicle_id IS NOT NULL)`.

**Indexes:**
- `(company_id, status)`, for the dashboard counts;
- `(company_id, driver_id, status)`;
- `(company_id, trip_date)`.

**Load vs trip:** one load per trip (decided Q6). "Load type" in the year-end report is `product` (decided C8). If several loads per trip are ever needed, `product`, `weight` and `load_pay` move into a `trip_loads` child table.

**Index for reports:** `(company_id, completed_at)`, used by profit and loss and the year-end report.

### `trip_stops` (Tenant child)

The planned route.

- **Columns:**
  - `id`, `company_id`, `trip_id`, `sequence` smallint;
  - `stop_type` enum(start, pickup, drop_off);
  - `also_pickup` bool (the start is also the pickup, client X1);
  - `shipper_id` (null);
  - `address_line`, `city`, `state_code`, `zip`, `latitude`, `longitude`, `google_place_id`;
  - `planned_at`, `arrived_at`, `departed_at`, timestamps.
- **FKs:** cFK `trip_id`, CASCADE; cFK `shipper_id`, RESTRICT.
- **Unique:** `(trip_id, sequence)`.
- **Index:** `(company_id, trip_id)`.
- **"Pending pickup"** = an assigned or in-progress trip whose pickup stop (or start stop with `also_pickup`) has no `departed_at`.
- **Unplanned stops** are `trip_events` of type `stop`.

### `inspection_templates`, `inspection_template_items` (Reference)

Platform-managed checklists, in line with LKP-01.

- **Templates columns:** `id`, `name`, `inspection_type` enum(pre_trip), `version`, `is_active`, `published_at`, timestamps.
- **Templates unique:** `(name, version)`.
- **Items columns:** `id`, `template_id` (CASCADE), `section`, `label`, `sort_order`, `is_required`, timestamps.
- **Versioning:** a template that has been used is never edited; changes publish a new version.
- Company-specific templates are out of scope until requested.
- **Not a DVIR (decided C10).** This is a pre-trip checklist. Post-trip DVIR sign-off and defect certification (§396.11) are not part of it.

### `inspections` (Tenant root)

- **Columns:**
  - `id`, `company_id`, `trip_id` (null), `driver_id`, `vehicle_id`, `trailer_id` (null), `template_id`;
  - `status` enum(in_progress, completed, abandoned);
  - `result` enum(no_defects, defects_found) (null);
  - `odometer`, `started_at`, `completed_at`, `performed_by_user_id`, `notes`, timestamps.
- **FKs:**
  - cFK trip, driver, vehicle, trailer, all RESTRICT;
  - `template_id`, RESTRICT;
  - `performed_by_user_id`, SET NULL.
- **Indexes:** `(company_id, trip_id)`, `(company_id, vehicle_id, completed_at)`.
- **Progress** = results answered ÷ template items.

### `inspection_results` (Tenant detail, via inspection)

- **Columns:** `id`, `inspection_id` (CASCADE), `template_item_id` (RESTRICT), `item_label` (snapshot), `result` enum(pass, fail, na), `note`, timestamps.
- **Unique:** `(inspection_id, template_item_id)`.

### `trip_events` (Tenant root)

Live events recorded during a trip.

**Columns:**
- `id`, `company_id`, `trip_id`, `driver_id`, `vehicle_id` (null).
- `type` enum(stop, police_stop, weigh_station, accident, breakdown, fuel).
- `occurred_at`, `latitude`, `longitude`, `address`, `city`, `state_code`.
- `reason` text, `ticket_issued` bool (null), `weight_ok` bool (null), `details` json.
- Accident-register fields (decided C9; the §390.15 report is generated from these):
  - `injuries_count` smallint unsigned (null);
  - `fatalities_count` smallint unsigned (null);
  - `hazmat_released` bool (null).
- `recorded_by_user_id`, `voided_at`, `void_reason`, timestamps.

**FKs:**
- cFK trip, driver, vehicle, all RESTRICT (evidence records);
- `recorded_by_user_id`, SET NULL.

**CHECK:**
- `type <> 'police_stop' OR (ticket_issued IS NOT NULL AND (ticket_issued = 1 OR reason IS NOT NULL))`;
- `type <> 'weigh_station' OR weight_ok IS NOT NULL`;
- `type <> 'stop' OR reason IS NOT NULL`;
- `type <> 'accident' OR (injuries_count IS NOT NULL AND fatalities_count IS NOT NULL AND hazmat_released IS NOT NULL)`;
- `type = 'accident' OR (injuries_count IS NULL AND fatalities_count IS NULL AND hazmat_released IS NULL)`.

**Indexes:** `(company_id, trip_id, occurred_at)`, `(company_id, type, occurred_at)`.

**Rules in the service:**
- A ticket file is required when `ticket_issued = 1`.
- Documents are required for a weigh station with `weight_ok = 0`, and for an accident (licence, insurance, photos).
- Evidence is never deleted; it is voided.

### `fuel_purchases` (Tenant root)

- **Columns:**
  - `id`, `company_id`, `trip_id` (null), `trip_event_id` (null), `driver_id`, `vehicle_id`, `fuel_type_id` (null);
  - `purchased_at`, `latitude`, `longitude`, `location_name`, `state_code`;
  - `gallons` decimal(10,3), `price_per_gallon` decimal(8,3), `total_amount` decimal(12,2), `currency`;
  - `odometer`, `confirmed_at`, `recorded_by_user_id`, timestamps.
- **FKs:**
  - cFK trip, trip event, driver, vehicle, all RESTRICT;
  - `fuel_type_id`, RESTRICT;
  - `recorded_by_user_id`, SET NULL.
- **Unique:** `trip_event_id`, `(company_id, id)`.
- **Indexes:** `(company_id, purchased_at)`, `(company_id, vehicle_id, purchased_at)`.
- **"Done" screen** = `confirmed_at` is set after the receipt documents are uploaded.

### `location_pings` (Tenant root, high volume)

- **Columns:** `id`, `company_id`, `trip_id` (null), `driver_id`, `vehicle_id` (null), `recorded_at` datetime(3), `received_at`, `latitude`, `longitude`, `speed_mph`, `heading`, `accuracy_m`.
- **No FKs [R].** Inserts arrive in batches at high volume, and rows are only removed by the retention job. Integrity is checked when a batch is written (the token's company and the driver's active trip).
- **Indexes:** `(trip_id, recorded_at)`, `(company_id, vehicle_id, recorded_at)`.
- **Retention:** [L] default 13 months, until counsel confirms. Partition by month once volume justifies it ([12](12-performance-plan.md)).

### `vehicle_last_locations` (Tenant child)

Feeds the live map.

- **Columns:** `vehicle_id` (PK), `company_id`, `trip_id` (null), `driver_id` (null), `latitude`, `longitude`, `recorded_at`, `updated_at`.
- **FK:** cFK `vehicle_id`, CASCADE.
- **Index:** `company_id`.
- **Trailer location** is derived from the vehicle pulling the trailer on its active trip (decided C5). There is no trailer GPS hardware.

---

## 10. Audit and support sessions

### `audit_logs` (Platform-owned; company rows readable by that company's Owner)

**Columns:**
- `id`, `company_id` (null for platform-only events);
- `actor_type` enum(user, platform_user, system, stripe), `actor_id`;
- `support_session_id` (null);
- `action` (e.g. `role.permissions_changed`), `subject_type`, `subject_id`;
- `changes` json (before and after; SSN, tokens, passwords and secrets are redacted);
- `ip`, `user_agent`, `created_at`.

**FKs:** none, so audit rows survive any purge of the records they describe.

**Indexes:** `(company_id, created_at)`, `(subject_type, subject_id)`, `(actor_type, actor_id, created_at)`.

**Rules:**
- **Append-only:** no update or delete path in the app.
- [R] The production DB user for the app should have no `UPDATE` or `DELETE` grant on this table. The retention job uses a separate role.
- **Always audited:**
  - login, logout, failed login, password reset, 2FA changes;
  - users created, invited, deactivated, or with changed email;
  - role, permission and assignment changes; ownership transfer;
  - company status changes;
  - changes to plans, prices, plan features, features and overrides;
  - every subscription change (including those made by webhooks);
  - the support-session lifecycle and **every** request made during a session;
  - exports and ZIP downloads;
  - driver hire, reject, terminate;
  - deletes, restores and voids.
  - DQ-document **downloads**, always; DQ-document **views** during support sessions [R].
- **Retention:** [L] default 2 years, until counsel confirms.

### `platform_support_sessions` (Platform)

Approved by **both** the company Owner and a second Platform Owner (decided Q1). See [04](04-platform-administration.md) §4.2 for the columns.

- **FKs:**
  - `company_id`, RESTRICT;
  - `platform_user_id`, RESTRICT;
  - `tenant_approved_by_user_id` and `platform_approved_by_platform_user_id`, RESTRICT (both are in the CHECK);
  - `revoked_by_user_id`, SET NULL.
- **CHECK:**
  - approved, active or ended sessions have both approvers;
  - the platform approver is not the requester.
- **Indexes:** `(company_id, status)`, `(platform_user_id, status)`.

---

## 11. Money

### `driver_pay_rates` (Tenant root)

- **Columns:** `id`, `company_id`, `driver_id`, `rate_type` enum(per_mile, hourly), `rate` decimal(10,4), `currency`, `effective_from` date, `effective_to` date (null), `created_by_user_id`, timestamps.
- **FKs:** cFK `driver_id`, RESTRICT; `created_by_user_id`, SET NULL.
- **Unique:** `(driver_id, rate_type, effective_from)`.
- **CHECK:** `effective_to IS NULL OR effective_to >= effective_from`.
- **Rule in the service:** no overlapping periods per (driver, rate type).
- **How per-mile and hourly rates combine (C1).** Engineering decision, under the Phase 4 instruction:
  - mileage pay = Σ `trips.paid_miles` for the driver's trips completed in the period × the per-mile rate in force on the completion date;
  - hourly pay covers `time_entries` of type `on_duty_not_driving` when the driver has a per-mile rate in force, and **all** work entries (`driving` + `on_duty_not_driving`) when the driver only has an hourly rate;
  - so a driving hour is never paid twice.
- Contractors (`drivers.worker_type = contractor`) are not paid through payroll.

### `expenses` (Tenant root)

- **Columns:** `id`, `company_id`, `trip_id` (null), `vehicle_id` (null), `driver_id` (null), `category` enum(toll, lumper, scale, parking, repair, other) [A], `amount`, `currency`, `incurred_on`, `description`, `created_by_user_id`, timestamps, `deleted_at`.
- **FKs:** cFK on trip, vehicle and driver, RESTRICT.
- **Index:** `(company_id, incurred_on)`.
- **Not copied here:** fuel and service costs. Profit and loss reads `fuel_purchases` and `service_logs` directly.

### `time_entries` (Tenant root)

- **Columns:**
  - `id`, `company_id`, `driver_id`, `trip_id` (null), `timesheet_id` (null);
  - `entry_type` enum(driving, on_duty_not_driving, break), `started_at`, `ended_at` (null), `source` enum(app, manual);
  - `open_driver_id` (generated: `CASE WHEN ended_at IS NULL THEN driver_id END`);
  - `created_by_user_id`, timestamps.
- **FKs:** cFK driver, trip and timesheet, RESTRICT.
- **Unique:** `open_driver_id` (one running entry per driver).
- **CHECK:** `ended_at IS NULL OR ended_at > started_at`.
- **Index:** `(company_id, driver_id, started_at)`.

### `timesheets` (Tenant root)

- **Columns:**
  - `id`, `company_id`, `driver_id`, `period_start`, `period_end`;
  - `regular_minutes`, `overtime_minutes`, `double_overtime_minutes`;
  - `status` enum(draft, submitted, approved, exported), `approved_by_user_id`, `approved_at`, timestamps.
- **FK:** cFK `driver_id`, RESTRICT.
- **Unique:** `(driver_id, period_start)`, `(company_id, id)`.

**How minutes are classified (C3, daily thresholds).** Thresholds come from `company_settings`.

1. Split work entries at midnight in the company time zone. Breaks are excluded.
2. For each workday, with `w` = minutes worked that day:
   - regular = min(w, OT threshold);
   - overtime = w beyond the OT threshold, up to the DT threshold;
   - double overtime = w beyond the DT threshold.
3. If a weekly threshold is set, regular minutes in the workweek beyond it move to overtime. Minutes already counted as daily overtime are never counted twice, so the higher of the daily and weekly result is paid.
4. For `overtime_exempt` drivers the minutes are still classified, for reporting, but no premium is paid.

The California 7th-consecutive-day rule is not built ([14](14-open-questions.md) §5). [L] Timesheets are not hours-of-service records.

### `payroll_runs` / `payroll_run_lines` (Tenant root / Tenant child)

- **Run columns:** `id`, `company_id`, `period_start`, `period_end`, `status` enum(draft, approved, exported, failed), `approved_by_user_id`, `exported_at`, timestamps.
- **Line columns:**
  - `id`, `company_id`, `payroll_run_id` (cFK, CASCADE), `driver_id` (cFK, RESTRICT), `timesheet_id` (cFK, RESTRICT, null);
  - quantities: `paid_miles`, `hourly_minutes`, `regular_minutes`, `overtime_minutes`, `double_overtime_minutes`;
  - rate snapshots: `per_mile_rate`, `hourly_rate`, `overtime_multiplier`, `double_overtime_multiplier`, `overtime_exempt`;
  - amounts: `mileage_pay`, `hourly_pay`, `regular_rate`, `overtime_premium`, `gross_amount`, `currency`, timestamps.
- **Unique:** `(payroll_run_id, driver_id)`.
- **Snapshots:** the rates are frozen in each line, so later rate changes never rewrite history.

**Overtime premium:**
- For hourly-only drivers, `regular_rate` = the hourly rate. The premium = OT hours × rate × (OT multiplier − 1) + DT hours × rate × (DT multiplier − 1), because those hours are already in the hourly pay.
- For drivers with mileage pay, `regular_rate` = (mileage pay + hourly pay) ÷ total hours worked, the piece-rate method of 29 CFR 778.111 [L]. The premium = OT hours × regular rate × (OT multiplier − 1) + DT hours × regular rate × (DT multiplier − 1).
- Exempt drivers: the premium is 0.

`gross_amount` = mileage pay + hourly pay + premium.

### Profit and loss

There is no table for this. A report query, by company time-zone day, computes:

**income**: `trips.load_pay` for trips completed that day

**minus costs**:
- `fuel_purchases.total_amount`
- `service_logs.total_cost`
- `expenses.amount`
- driver pay for the day (payroll lines, or computed from rates when there is no run yet)

Variable costs only; fixed costs such as insurance and truck payments are excluded (decided C2). Driver pay for days without an approved payroll run is estimated with the same formulas and marked "estimated" in the report. A daily rollup table is added only if the report becomes slow ([12](12-performance-plan.md)).

---

## 12. Communication, integrations, exports

### `conversations` (Tenant root)

- **Columns:** `id`, `company_id`, `type` enum(direct, group, trip), `trip_id` (null), `title`, `created_by_user_id`, `last_message_at`, timestamps.
- **FK:** cFK `trip_id`, RESTRICT.
- **Index:** `(company_id, last_message_at)`.

### `conversation_participants` (Tenant child)

- **PK:** `(conversation_id, user_id)`.
- **Columns:** `company_id`, `last_read_message_id`, `joined_at`, `left_at`.
- **FKs:** cFK `conversation_id`, CASCADE; cFK `user_id`, CASCADE.

### `messages` (Tenant root)

- **Columns:** `id`, `company_id`, `conversation_id`, `sender_user_id`, `body`, `sent_at`, `edited_at`, `deleted_at`.
- **FKs:** cFK `conversation_id`, RESTRICT; `sender_user_id`, SET NULL.
- **Index:** `(conversation_id, id)`.

### `time_off_requests` (Tenant root)

- **Columns:**
  - `id`, `company_id`, `requested_by_user_id`, `driver_id` (null);
  - `starts_on`, `ends_on`, `type` enum(vacation, sick, personal, other) [A], `reason`;
  - `status` enum(pending, approved, rejected, cancelled), `reviewed_by_user_id`, `reviewed_at`, `review_note`, timestamps.
- **FKs:** cFK `requested_by_user_id`, RESTRICT; cFK `driver_id`, RESTRICT; `reviewed_by_user_id`, SET NULL.
- **CHECK:** `ends_on >= starts_on`.
- **Index:** `(company_id, status, starts_on)`.
- **Who submits and approves (decided C6):** drivers and staff submit (`time-off.request.own`); an Owner or Administrator approves (`time-off.approve`).

### `integration_connections` (Tenant root)

- **Columns:**
  - `id`, `company_id`, `provider` enum(quickbooks_online), `external_account_id`;
  - `access_token`, `refresh_token` (encrypted text);
  - `token_expires_at`, `refresh_token_expires_at`;
  - `status` enum(connected, expired, revoked, error);
  - `connected_by_user_id`, `connected_at`, `disconnected_at`, timestamps.
- **Unique:** `(company_id, provider)`, `(company_id, id)`.
- **What is synced (decided C4, QuickBooks Online):**
  - Employee drivers (`worker_type = employee`) become QBO **Employees**.
  - Approved timesheets become **TimeActivity** records, with regular, OT and DT as separate entries.
  - Mileage pay and OT premiums can't be sent through the Accounting API, because pay data is behind Intuit's gated Payroll API [A]. They are delivered in a payroll-run summary export (`exports.type = payroll_summary`) for the payroll administrator.

### `external_references` (Tenant root)

Maps local records to external ids.

- **Columns:** `id`, `company_id`, `provider`, `local_type` enum(driver, payroll_run, timesheet), `local_id`, `external_id`, `synced_at`, timestamps.
- **Unique:** `(company_id, provider, local_type, local_id)`; `(company_id, provider, local_type, external_id)`.
- **No FK on `local_id`:** it is typed by `local_type`. A nightly integrity query checks it.

### `integration_sync_logs` (Tenant child)

- **Columns:** `id`, `company_id`, `integration_connection_id`, `direction` enum(push, pull), `entity_type`, `entity_id`, `status` enum(success, failed), `error`, `created_at`.
- **FK:** cFK `integration_connection_id`, CASCADE.
- **Index:** `(company_id, created_at)`.

### `exports` (Tenant root)

- **Columns:**
  - `id`, `company_id`, `requested_by_user_id`;
  - `type` enum(zip_driver, zip_trip, zip_company, year_end_report, profit_loss_report, payroll_summary, accident_register), `parameters` json;
  - `status` enum(queued, running, ready, failed, expired);
  - `file_id` (null), `error`;
  - `started_at`, `finished_at`, `expires_at`, timestamps.
- **FKs:** cFK `file_id`, RESTRICT; `requested_by_user_id`, SET NULL.
- **Index:** `(company_id, status, created_at)`.

---

## 13. Reference and framework

| Table | Notes |
|---|---|
| `countries` | Existing. `iso_code` unique [V]. |
| `states` | Existing. Add unique `(country_id, code)`; drop the redundant `country_id` index. `state_code` columns are validated against `states.code`, with no FK (D7). |
| `notifications` | Existing, Laravel database channel |
| `otp_verifications` | Existing |
| `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks` | Existing |
| `personal_access_tokens` | New (Sanctum) |

## 14. Existing tables: retain, modify, split, merge, replace

| Table | Verdict | Reason |
|---|---|---|
| `users` | Modify | Add `company_id`, `user_type`, `phone`, `last_login_at`. Drop the Stripe columns. |
| `companies` | Modify | Owner FK without cascade, status lifecycle, billing columns, contact email no longer synced (decided D3b) |
| `roles`, `permissions`, `model_has_*`, `role_has_permissions` | Modify | Spatie teams, extra columns, composite FK, resolver indexes. Checks are database-first. |
| `policy_pdfs` | Modify | `company_id` NOT NULL; platform defaults move to `site_settings`; files via `stored_files` |
| `drivers` | Modify (status **split**) | Login link, creator without cascade, two status columns, termination, worker type, overtime exemption |
| `licenses`, `accidents`, `violations`, `experiences`, `forfeitures`, `residence_addresses`, `employment_records` | Modify | Types, codes, current-licence flag, duplicate fields removed |
| `driver_documents` | Modify | Unique per driver, file ids, declaration flags |
| `driver_compliance_documents`, `vehicle_documents`, `trailer_documents` | Modify (kept as typed tables, decided D5a) | `company_id`, `file_id`, issue dates, soft deletes |
| `service_documents` | Modify | File metadata moves to `stored_files` |
| `vehicles`, `trailers` | Modify | Plate, photo, RESTRICT, rename |
| `asset_groups` | Modify | Snapshot and free-text driver columns replaced by ids; one active group per asset |
| `service_logs`, `maintenance_schedules`, `service_log_category` | Modify | cFK, RESTRICT, schedule link, drop `is_active` |
| `document_types` and the lookups | Modify | Unique names, `is_active`, `system_key`; trait fix |
| `plans` | **Split** into `plans` + `plan_prices` (+ `plan_features`) | Prices per interval; entitlements |
| `subscriptions`, `payments`, `subscription_notification_logs` | Modify (**re-keyed** to company) | Company-owned billing |
| `site_settings` | Modify | Platform defaults, billing settings, platform identity, theme, SEO defaults, mail sender |
| `countries`, `states`, `otp_verifications`, `notifications`, framework tables | Retain | — |
| Every new table in sections 3–12 | New | Required by the client and the architecture |

## 15. Retention defaults to confirm [L]

| Records | Default | Basis to confirm with counsel |
|---|---|---|
| DQ file (drivers and application tables, `driver_documents`, driver compliance documents) | Length of employment + 3 years | 49 CFR 391.51 |
| Vehicle maintenance (service logs and documents, schedules, inspections) | 1 year, and 6 months after the vehicle leaves the carrier's control | 49 CFR 396.3 |
| Pre-trip inspection records | Same as maintenance records [R]. They are not DVIRs (decided C10), so the 3-month DVIR rule (49 CFR 396.11) does not apply. | Internal policy |
| Accident events | 3 years | 49 CFR 390.15 accident register |
| Financial and payroll (trips with pay, fuel, expenses, timesheets, payroll, payments) | 7 years [A] | Tax and payroll record rules |
| Location pings | 13 months [A] | Privacy and state tracking rules |
| Audit logs | 2 years [A] | Internal policy |
| Messages | Life of the company [A] | — |

The purge job (`companies:purge`, platform-only) deletes a deactivated company's data only after every applicable period has passed. Files are deleted after the database transaction commits.

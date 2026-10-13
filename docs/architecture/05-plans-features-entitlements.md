# 05 · Plans, features and entitlements

## 1. Five different questions

| Question | Answered by | Who controls it |
|---|---|---|
| Does the feature exist on the platform? | The code registry, synced to `features`, plus `features.availability` | Developers (existence), Platform Owner/Admin (availability) |
| Is it included in the plan? | `plan_features` | Platform Owner/Admin |
| Is it switched on or off for this company? | `company_feature_overrides` (open row) | Platform Owner/Admin/Billing |
| Is the company in good standing? | Access state, derived from `companies.status` and the subscription ([07](07-subscription-billing.md)) | Billing events and platform staff |
| May this user do it? | Spatie permission in the company's team ([06](06-roles-permissions.md)) | Company administrators |

None of these answers is enough on its own. A menu item being visible, or a user holding a permission, never grants access to a feature the company is not entitled to.

Today none of this exists [V]. `plans.features` is a JSON list of marketing bullets (`SubscriptionAdminController.php:267`), and nothing enforces limits.

## 2. Feature registry

- Features are defined in code as an enum, `App\Features\Feature`. Each case carries its key, group, kind, unit, and the permissions that belong to it.
- A seeder, `FeatureRegistrySync`, upserts the enum into `features` on deploy.
- A feature cannot be created from the UI, because code has to implement it.
- A feature removed from the code is set to `availability = disabled`, never deleted. Plan history keeps referring to it.

### Catalogue

The catalogue is derived from the client documents and the existing app.

| Key | Kind | Group | Covers |
|---|---|---|---|
| `drivers` | module | Core | Drivers and the DQ file wizard |
| `driver_applications` | module | Core | Public apply link, OTP, resume, status |
| `driver_compliance` | module | Compliance | Driver compliance dashboard and uploads |
| `fleet` | module | Core | Vehicles, trailers, asset groups |
| `fleet_compliance` | module | Compliance | Fleet compliance dashboard and uploads |
| `maintenance` | module | Fleet | Service logs and schedules |
| `compliance_reminders` | module | Compliance | Daily digest and one-click reminders |
| `custom_roles` | module | Account | Creating and editing company roles and per-user grants. Without it, only the system roles are available. |
| `document_folders` | module | Documents | Custom folders and folder uploads |
| `zip_export` | module | Documents | ZIP per driver, trip, company |
| `trips` | module | Operations | Trips, stops, shippers, assignment, dashboard counts |
| `pre_trip_inspections` | module | Operations | Checklists |
| `trip_events` | module | Operations | Stops, police, weigh station, accident, breakdown, fuel |
| `mobile_app` | module | Operations | Driver API access |
| `gps_tracking` | module | Operations | Location pings, live map, miles |
| `messaging` | module | Communication | Conversations and push notifications |
| `time_off` | module | Communication | Time-off requests |
| `driver_pay` | module | Money | Load pay, pay rates |
| `timesheets` | module | Money | Time entries and timesheets |
| `profit_loss` | module | Money | Daily profit and loss, reports |
| `year_end_reports` | module | Money | Year-end exports |
| `quickbooks` | module | Integrations | QuickBooks connection and sync |
| `max_users` | limit | Limits | Office users (count, unit: users) |
| `max_drivers` | limit | Limits | Drivers (count) |
| `max_vehicles` | limit | Limits | Vehicles (count) |
| `max_trailers` | limit | Limits | Trailers (count) |
| `storage_gb` | limit | Limits | Stored file bytes |

Social login, 2FA and image-to-PDF are **platform capabilities**, not plan features. They are configured in `site_settings` or `config`.

**Which plan includes which feature** is a commercial decision for the Platform Owner. The seeder only creates a starting configuration that mirrors today's three tiers. For example: Starter = core + compliance + 10 drivers; Company adds `maintenance`, `trips` and `document_folders`; Enterprise adds everything. [A] These amounts are placeholders.

## 3. Schema

There is one source of truth per concern. Full columns are in [08-database-schema.md](08-database-schema.md).

| Table | Source of truth for | Key rules |
|---|---|---|
| `features` | Which features exist and whether each is released | PK `key`. `availability` is one of `general` (normal), `override_only` (beta: only through an override) or `disabled` (off for everyone). |
| `plans` | The product: name, description, public/active, sort order, display bullets | `slug` unique. `marketing_bullets` json is for display only. |
| `plan_prices` | What a plan costs per interval and currency | Immutable once a subscription uses it. A price change inserts a new row and archives the old one. At most one current price per (plan, interval, currency). [07](07-subscription-billing.md) §3 |
| `plan_features` | What a plan includes | PK `(plan_id, feature_key)`. `enabled` bool, `limit_value` (null = unlimited). A missing row means not included. |
| `company_feature_overrides` | Platform exceptions for one company, with history | Append-only. At most one open row per (company, feature) (section 6). |

Tables removed from earlier drafts:
- `plan_entitlements` and `company_entitlement_overrides`, replaced by the two tables above;
- `company_feature_preferences`, because no requirement asks for tenants to switch features off;
- the entitlement use of `plans.features`.

## 4. Precedence

`Entitlements::for($company)` resolves every feature the same way. The first rule that applies wins.

| # | Rule | Result |
|---|---|---|
| 1 | `features.availability = disabled` | **Off**. Overrides cannot enable it. |
| 2 | Access state is `blocked` (company suspended or deactivated, or subscription lapsed beyond the policy) | **Off** |
| 3 | An effective override exists (open, `starts_at <= now`, `ends_at` null or in the future) | Its non-null `enabled` / `limit_value` replace the plan's values. Null fields inherit from the plan. |
| 4 | `availability = override_only` and there is no override | **Off** |
| 5 | The company has no current subscription | **Off.** Only account and billing pages are reachable. |
| 6 | A `plan_features` row exists for the subscription's plan | Its `enabled` and `limit_value` apply |
| 7 | No row | **Off** |

The **request-time checks** run afterwards in `Gate::before` ([06](06-roles-permissions.md)):

8. The ability's feature must be on (rules 1–7).
9. Access state `read_only` denies any permission with `is_write = true`.
10. The user must hold the Spatie permission in the company's team.
11. Creating a counted resource also passes the limit check (section 5).

**Caching.** The resolved set is cached under `tenant:{company_id}:entitlements:{companies.entitlements_version}`. These events increment `companies.entitlements_version` in the same transaction:
- a plan feature or price is changed (every company on that plan);
- an override is added or revoked;
- the subscription's plan, price or status changes;
- the company status changes.

Overrides that expire with time are picked up because the cache entry lives at most until the earliest `ends_at` among that company's open overrides.

### Enforcement points

| Entry point | Mechanism |
|---|---|
| Web routes | `feature:trips` middleware on route groups, plus `Gate::before` on every permission |
| API routes | Same middleware |
| Blade | `@feature('trips')` to show or hide (display only) |
| Policies | Receive entitlement-aware results through `Gate::before` |
| Queued jobs | `TenantAware` middleware re-checks the feature the job declares (`public string $feature = 'gps_tracking'`) |
| Scheduler | Loops only over companies entitled to the feature (for example, the digest only for `compliance_reminders`) |
| Exports and reports | The export job declares its feature, and the download route checks it again |
| Public application | `driver_applications` is checked by `ResolvePublicCompany`. If the feature is off, the apply link shows "applications closed". |

## 5. Resource limits

| Limit | What counts | Notes (decided Q4) |
|---|---|---|
| `max_users` | `users` with `company_id = X`, `status = active`, `user_type = member`, and no linked driver record | Driver logins and support users don't count |
| `max_drivers` | `drivers` with `company_id = X`, not soft-deleted, `employment_status = active` | Applicants, drafts, rejected and terminated drivers don't count |
| `max_vehicles` | `vehicles` not soft-deleted | — |
| `max_trailers` | `trailers` not soft-deleted | — |
| `storage_gb` | `companies.storage_bytes` | — |

How a limit is enforced:

1. Every path that can raise a count calls `Limits::assertCanCreate($company, $key, $increment = 1)` inside its database transaction. The paths are:
   - creating;
   - restoring a soft-deleted record;
   - hiring, which moves a driver into the counted state;
   - accepting an invitation;
   - re-activating a user;
   - imports.
2. The method runs `SELECT … FOR UPDATE` on the `companies` row, counts, compares with the effective limit, and throws `LimitReached` (HTTP 422 with an upgrade hint) when the limit is reached.
3. Because every path locks the same company row first, two parallel creates cannot both pass at limit − 1.
4. Storage: `companies.storage_bytes` is increased in the same transaction that inserts a `stored_files` row, and decreased when a file is purged. The upload is rejected **before** the file is written if `storage_bytes + size` would exceed the limit.
5. A nightly job recomputes the counters and storage and logs any drift.

## 6. Overrides

`company_feature_overrides` is **append-only** history:

| Column | Notes |
|---|---|
| `company_id`, `feature_key` | Which company and feature |
| `enabled` | nullable. Null inherits the plan; true or false overrides it. |
| `limit_value` | nullable. Null inherits; a number overrides. "Unlimited" uses `is_unlimited = true`. |
| `is_unlimited` | bool, default false. Needed because null already means "inherit". |
| `reason` | required |
| `starts_at`, `ends_at` | `ends_at` nullable |
| `granted_by_platform_user_id` | |
| `revoked_at`, `revoked_by_platform_user_id`, `revoke_reason` | |
| `open_flag` | Generated column: `CASE WHEN revoked_at IS NULL THEN 1 END` |

Rules:
- A unique index on `(company_id, feature_key, open_flag)` allows at most one non-revoked row. MySQL allows any number of NULLs in a unique index, so revoked history is unlimited.
- To change an override, revoke the open row and insert a new one, in one transaction. Rows are never updated otherwise.
- An expired override (`ends_at` in the past) stays open but has no effect. A nightly job revokes expired rows with `revoke_reason = 'expired'`, so the history is explicit.
- Every insert and revoke is audited ([08](08-database-schema.md) §10).

## 7. Changes to plans and subscriptions

### 7.1 Platform Owner edits a plan

| Change | Effect |
|---|---|
| Name, description, bullets | Immediate. Display only. |
| `plan_features` row (enable or disable a module, change a limit) | **Applies immediately** to every company on the plan (decided Q3). Before saving, the UI shows how many companies are affected and how many would exceed a lowered limit. To grandfather specific companies, add overrides first. |
| A new price | Insert a new `plan_prices` row and create the Stripe Price. The old row is archived. Existing subscriptions keep the archived price until a Billing Manager moves them ([07](07-subscription-billing.md) §3). |
| Deactivating a plan (`is_active = false`) | Hidden from checkout. Existing subscriptions continue. |
| Deleting a plan | Not allowed once it has a subscription (FK `RESTRICT`) |

### 7.2 Company upgrades or downgrades

- An upgrade or downgrade changes the price on the existing Stripe subscription. It never creates a second subscription.
- Entitlements switch only when the Stripe webhook confirms the change.
- **Upgrade:** immediate, with Stripe proration (decided Q3).
- **Downgrade:** takes effect at the end of the current billing period (decided Q3). It is scheduled in Stripe as a price change at the renewal date. The company keeps its current entitlements until the webhook confirms the change.

When the new entitlements take effect:

| Situation | Behaviour |
|---|---|
| Over a limit (e.g. 15 drivers on a 10-driver plan) | Nothing is deleted or deactivated. Creating, restoring and hiring are blocked until the company is under the limit. A dashboard banner shows the overage. |
| A module is no longer included | Decided Q3: the module's data becomes **read-only and exportable**. Write permissions for that feature are denied, read and export are allowed. Driver mobile features stop. |
| A module is added | Available immediately after the webhook |

### 7.3 Subscription expiry, grace and suspension

See [07-subscription-billing.md](07-subscription-billing.md) §4. The access state feeds rule 2 and check 9 above.

## 8. Audit

Every change to the following writes an `audit_logs` row with before and after values, so the entitlement history of any company can be rebuilt:
- `features`;
- `plans`, `plan_prices`, `plan_features`;
- `company_feature_overrides`;
- subscriptions.

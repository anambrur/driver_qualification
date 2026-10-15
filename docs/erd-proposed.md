# DriverFilesHub: proposed data model

These diagrams show the **target** schema for the multi-tenant redesign. The built schema is documented in [erd.md](erd.md), so the two can be compared side by side.

- Full column, constraint and index specifications are in [architecture/08-database-schema.md](architecture/08-database-schema.md).
- The reason for each change to an existing table is in [architecture/09-erd-audit.md](architecture/09-erd-audit.md).
- Status: decisions applied 13 October 2026. The Phase 0 tables are built (15 October 2026, see [erd.md](erd.md)); the tables of later phases are not.

## How it fits together

- **Platform tables.** These belong to the SaaS operator, not to any tenant: platform staff, features, plans, prices, Stripe events, support sessions, audit.
- **The company is the tenant.** Every user, driver, asset, trip, file and financial record belongs to exactly one company.
- **Billing belongs to the company:** subscription, payments and feature overrides. The plan's features and limits decide what the company can use.
- **Roles are Spatie roles in the company's team** (`team_id = company_id`). Platform roles live in team 0. Permission checks query these tables directly in the user's own team, with no global role cache.
- **Same-company references are enforced by the database.** References between tenant tables use composite foreign keys `(company_id, x_id)`, so a row cannot point at another company's record.

## Reading the diagrams

| Notation | Meaning |
|---|---|
| `\|\|--o{` | one to zero-or-many (FK required) |
| `\|o--o{` | optional parent (FK nullable) |
| `\|\|--o\|` | one to zero-or-one |
| solid line | enforced by a foreign key |
| dotted line | logical link only (polymorphic, generated column, or no FK on purpose) |
| `cFK` in a comment | composite tenant FK `(company_id, x_id)` to `x(company_id, id)` |
| `PK` `FK` `UK` | primary, foreign, unique key |

Every table also has `created_at` / `updated_at` unless its diagram says otherwise. Every tenant parent has `UNIQUE(company_id, id)`. Neither is drawn.

## Overview

The main business tables, relationships only.

```mermaid
erDiagram
  plans ||--o{ plan_prices : "priced"
  plans ||--o{ plan_features : "includes"
  features ||--o{ plan_features : "in plan"
  features ||--o{ company_feature_overrides : "overridden"
  companies ||--o{ company_feature_overrides : "exceptions"
  companies ||--o{ subscriptions : "subscribes"
  plan_prices ||--o{ subscriptions : "billed at"
  subscriptions |o--o{ payments : "invoices"
  companies ||--o{ users : "members"
  companies |o..o| users : "owner_user_id"
  companies ||--o{ roles : "team roles"
  roles ||--o{ model_has_roles : "assigned"
  companies ||--o{ drivers : "employs"
  users |o--o| drivers : "driver login"
  drivers ||--o{ licenses : ""
  drivers ||--o{ driver_compliance_documents : ""
  companies ||--o{ vehicles : "fleet"
  companies ||--o{ trailers : "fleet"
  vehicles ||--o{ vehicle_documents : ""
  trailers ||--o{ trailer_documents : ""
  drivers ||--o{ asset_groups : "default pairing"
  vehicles ||--o{ service_logs : "serviced"
  companies ||--o{ stored_files : "stores"
  companies ||--o{ folders : ""
  folders |o--o{ documents : "filed in"
  stored_files ||--o{ documents : "content"
  companies ||--o{ shippers : ""
  companies ||--o{ trips : ""
  drivers |o--o{ trips : "assigned"
  vehicles |o--o{ trips : "tractor"
  trailers |o--o{ trips : "trailer"
  trips ||--o{ trip_stops : "route"
  trips ||--o{ trip_events : "events"
  trips |o--o{ inspections : "pre-trip"
  trip_events |o--o| fuel_purchases : "fuel stop"
  trips |o..o{ location_pings : "tracked"
  drivers ||--o{ driver_pay_rates : ""
  drivers ||--o{ timesheets : ""
  payroll_runs ||--o{ payroll_run_lines : ""
  companies ||--o{ conversations : ""
  conversations ||--o{ messages : ""
  companies ||--o{ audit_logs : ""
  companies ||--o{ platform_support_sessions : "support access"
  platform_users ||--o{ platform_support_sessions : "requests"
```

## Platform, plans and billing

```mermaid
erDiagram
  platform_users {
    bigint id PK
    string email UK
    enum status "active / inactive"
    text two_factor_secret "encrypted"
    timestamp last_login_at
  }
  features {
    string key PK "code registry"
    string name
    string group
    enum kind "module / limit"
    enum availability "general / override_only / disabled"
  }
  plans {
    bigint id PK
    string name
    string slug UK
    boolean is_public
    boolean is_active
    json marketing_bullets "display only"
  }
  plan_prices {
    bigint id PK
    bigint plan_id FK "restrict"
    enum interval "month / year"
    decimal amount
    char currency
    string stripe_price_id UK
    timestamp archived_at "immutable rows"
    int current_flag "generated, UK with plan interval currency"
  }
  plan_features {
    bigint plan_id PK, FK "cascade"
    string feature_key PK, FK "restrict"
    boolean enabled
    int limit_value "null = unlimited"
  }
  company_feature_overrides {
    bigint id PK
    bigint company_id FK "restrict"
    string feature_key FK "restrict"
    boolean enabled "null = inherit"
    int limit_value "null = inherit"
    boolean is_unlimited
    text reason
    timestamp starts_at
    timestamp ends_at
    bigint granted_by_platform_user_id FK "set null"
    timestamp revoked_at "append-only history"
    int open_flag "generated, UK with company and feature"
  }
  subscriptions {
    bigint id PK
    bigint company_id FK "restrict"
    bigint actor_user_id FK "set null"
    bigint plan_price_id FK "restrict"
    string stripe_subscription_id UK
    string stripe_price_id "snapshot"
    decimal amount "snapshot"
    enum status "trialing ... canceled"
    string source "stripe / trial / admin"
    timestamp trial_ends_at
    timestamp current_period_end
    timestamp ends_at
    timestamp ended_at
    int open_flag "generated, UK with company"
    timestamp last_stripe_event_at "ordering guard"
    boolean needs_review
  }
  payments {
    bigint id PK
    bigint company_id FK "restrict"
    bigint subscription_id FK "restrict, nullable"
    string stripe_invoice_id UK
    decimal amount
    string status
    timestamp paid_at
  }
  subscription_notification_logs {
    bigint id PK
    bigint company_id FK "restrict"
    bigint subscription_id FK "cascade"
    bigint recipient_user_id FK "set null"
    string type
    string channel
    tinyint days_before
  }
  stripe_events {
    bigint id PK
    string event_id UK
    string type
    timestamp stripe_created_at
    enum status "pending / processed / ignored / failed"
    json payload
  }
  asset_transfers {
    bigint id PK
    enum asset_type "vehicle / trailer"
    bigint source_company_id FK "restrict"
    bigint target_company_id FK "restrict"
    bigint source_vehicle_id "cFK with source company, restrict"
    bigint target_vehicle_id "cFK with target company, restrict"
    bigint source_trailer_id "cFK, restrict"
    bigint target_trailer_id "cFK, restrict"
    text reason
    json copied_counts
    bigint performed_by_platform_user_id FK "restrict"
    timestamp performed_at
  }
  platform_users {
    bigint id PK
  }
  site_settings {
    bigint id PK
    bigint trial_plan_id FK "restrict, nullable"
    int trial_days
    int grace_days
    string default_alcohol_drug_test_policy_pdf
    string default_general_work_policy_pdf
  }
  companies {
    bigint id PK
  }
  plans ||--o{ plan_prices : "priced"
  plans ||--o{ plan_features : "includes"
  features ||--o{ plan_features : "in"
  features ||--o{ company_feature_overrides : "for"
  companies ||--o{ company_feature_overrides : "has"
  platform_users |o--o{ company_feature_overrides : "granted"
  companies ||--o{ subscriptions : "owns"
  plan_prices ||--o{ subscriptions : "at price"
  companies ||--o{ payments : "pays"
  subscriptions |o--o{ payments : "billed"
  subscriptions ||--o{ subscription_notification_logs : "reminders"
  plans |o--o| site_settings : "trial plan"
  companies ||--o{ asset_transfers : "source"
  companies ||--o{ asset_transfers : "target"
  platform_users ||--o{ asset_transfers : "performed"
```

## Accounts, tenancy and authorization

```mermaid
erDiagram
  companies {
    bigint id PK
    bigint owner_user_id "cFK id+owner to users company_id+id, restrict"
    string company_name
    string slug UK "not null"
    string email "contact, not unique"
    string billing_email
    char state_code
    string timezone
    enum status "active / suspended / deactivated"
    string stripe_customer_id UK
    timestamp trial_used_at
    int entitlements_version
    bigint storage_bytes
  }
  company_settings {
    bigint company_id PK, FK "cascade"
    string trip_number_prefix
    enum distance_unit "mi / km"
    enum weight_unit "lb / kg"
    smallint overtime_daily_after_minutes "default 480"
    smallint double_overtime_daily_after_minutes "default 720"
    smallint overtime_weekly_after_minutes "optional"
    decimal overtime_multiplier "1.5"
    decimal double_overtime_multiplier "2.0"
    tinyint week_starts_on
  }
  users {
    bigint id PK
    bigint company_id FK "restrict, not null"
    enum user_type "member / support"
    string email UK "global, lowercase"
    timestamp email_verified_at
    enum status "active / inactive"
    timestamp last_login_at
  }
  company_invitations {
    bigint id PK
    bigint company_id FK
    string email
    bigint role_id "FK team_id+id to roles, cascade"
    bigint driver_id "cFK, restrict, nullable"
    char token_hash UK
    timestamp expires_at
    timestamp accepted_at
  }
  social_accounts {
    bigint id PK
    bigint user_id FK "cascade"
    enum provider "google / facebook / apple"
    string provider_user_id "UK with provider"
  }
  device_tokens {
    bigint id PK
    bigint company_id FK
    bigint user_id "cFK, cascade"
    enum platform "ios / android"
    string token UK
  }
  roles {
    bigint id PK
    bigint team_id "company id, 0 = platform, never null"
    string name "UK with team and guard"
    string guard_name "web / platform"
    string system_key "owner / driver / support_read ..."
    boolean is_system
  }
  permissions {
    bigint id PK
    string name "UK with guard"
    string guard_name
    string feature_key FK "restrict"
    boolean is_write
    boolean self_scoped
  }
  model_has_roles {
    bigint team_id PK
    bigint role_id PK "FK team_id+role_id to roles, cascade"
    string model_type PK "User or PlatformUser"
    bigint model_id PK "no FK, integrity query"
  }
  model_has_permissions {
    bigint team_id PK
    bigint permission_id PK, FK "cascade"
    string model_type PK
    bigint model_id PK
  }
  role_has_permissions {
    bigint permission_id PK, FK "cascade"
    bigint role_id PK, FK "cascade"
  }
  platform_users {
    bigint id PK
  }
  companies ||--|| company_settings : "settings"
  companies ||--o{ users : "members"
  companies |o..o| users : "owner"
  companies ||--o{ company_invitations : "invites"
  users ||--o{ social_accounts : "logins"
  users ||--o{ device_tokens : "push"
  companies ||..o{ roles : "team_id"
  roles ||--o{ model_has_roles : "assigned"
  users ||..o{ model_has_roles : "model_id"
  platform_users ||..o{ model_has_roles : "model_id, team 0"
  permissions ||--o{ model_has_permissions : "granted"
  roles ||--o{ role_has_permissions : "bundles"
  permissions ||--o{ role_has_permissions : "in"
  roles ||--o{ company_invitations : "offered"
```

## Drivers and the DQ file

```mermaid
erDiagram
  drivers {
    bigint id PK
    bigint company_id FK "restrict"
    bigint created_by_user_id FK "set null"
    bigint user_id UK "cFK login, restrict"
    string first_name
    string last_name
    date date_of_birth
    string ssn "encrypted"
    string main_phone
    string email "UK with company_id"
    bigint photo_file_id "cFK stored_files, restrict"
    char state_code
    enum application_status "draft ... withdrawn"
    enum employment_status "applicant / active / inactive / terminated"
    enum worker_type "employee / contractor"
    boolean overtime_exempt "company decides"
    string source "admin / public_application"
    timestamp hired_at
    date hire_date
    timestamp terminated_at
    bigint decided_by_user_id FK "set null"
    timestamp deleted_at
  }
  licenses {
    bigint id PK
    bigint driver_id FK "restrict"
    string first_name "snapshot as printed"
    string last_name "snapshot as printed"
    date expires
    char state_code
    string class
    string license_number
    boolean is_current
    bigint current_driver_id UK "generated"
  }
  residence_addresses {
    bigint id PK
    bigint driver_id FK "cascade"
    char state_code
    boolean is_current
  }
  experiences {
    bigint id PK
    bigint driver_id FK "cascade"
    string equipment_class
    int approx_miles
  }
  accidents {
    bigint id PK
    bigint driver_id FK "cascade"
    date accident_date
    smallint number_of_injuries
    smallint number_of_fatalities
  }
  violations {
    bigint id PK
    bigint driver_id FK "cascade"
    date violation_date
    string vehicle_type "snapshot text"
  }
  forfeitures {
    bigint id PK
    bigint driver_id FK "cascade"
    boolean denied_license
    boolean license_revoked
  }
  employment_records {
    bigint id PK
    bigint driver_id FK "cascade"
    string employer_name "external, snapshot"
  }
  driver_documents {
    bigint id PK
    bigint driver_id UK "FK cascade, one per driver"
    bigint license_front_file_id FK "restrict"
    bigint medical_card_file_id FK "restrict"
    boolean has_accidents
    boolean has_violations
    string applicant_signature
    date date_signed
  }
  driver_compliance_documents {
    bigint id PK
    bigint company_id FK
    bigint driver_id "cFK, cascade"
    bigint document_type_id FK "restrict"
    bigint file_id "cFK stored_files, restrict"
    date file_date
    date expiry_date
    timestamp deleted_at
  }
  document_types {
    bigint id PK
    string name "UK with module"
    string module "driver / vehicle / trailer"
    string system_key UK
    boolean requires_expiry
  }
  company_document_type {
    bigint id PK
    bigint company_id FK "cascade"
    bigint document_type_id FK "cascade"
  }
  policy_pdfs {
    bigint id PK
    bigint company_id UK "FK restrict, not null"
    bigint alcohol_drug_test_policy_file_id "cFK"
    bigint general_work_policy_file_id "cFK"
  }
  companies {
    bigint id PK
  }
  users {
    bigint id PK
  }
  companies ||--o{ drivers : "employs"
  users |o--o| drivers : "login"
  users |o--o{ drivers : "created by"
  drivers ||--o{ licenses : "holds"
  drivers ||--o{ residence_addresses : "lived at"
  drivers ||--o{ experiences : ""
  drivers ||--o{ accidents : "declared"
  drivers ||--o{ violations : "declared"
  drivers ||--o{ forfeitures : ""
  drivers ||--o{ employment_records : ""
  drivers ||--o| driver_documents : "signed"
  drivers ||--o{ driver_compliance_documents : "files"
  document_types ||--o{ driver_compliance_documents : "typed"
  companies ||--o{ company_document_type : "opts out"
  document_types ||--o{ company_document_type : ""
  companies ||--o| policy_pdfs : "policies"
```

## Fleet and maintenance

```mermaid
erDiagram
  vehicles {
    bigint id PK
    bigint company_id FK "restrict"
    string unit_no "UK with company_id"
    string vin "UK with company_id"
    string plate_number
    char plate_state_code
    bigint photo_file_id "cFK, restrict"
    bigint vehicle_type_id FK "restrict"
    bigint vehicle_group_id FK "restrict"
    bigint fuel_type_id FK "restrict"
    bigint odometer
    int engine_hours
    timestamp deleted_at
  }
  trailers {
    bigint id PK
    bigint company_id FK "restrict"
    string unit_no "UK with company_id"
    string vin "UK with company_id"
    string plate_number
    char plate_state_code
    bigint photo_file_id "cFK, restrict"
    bigint equipment_type_id FK "restrict, renamed"
    bigint vehicle_group_id FK "restrict"
    timestamp deleted_at
  }
  asset_groups {
    bigint id PK
    bigint company_id FK
    string group_name "UK with company_id"
    bigint driver_id "cFK, restrict"
    bigint co_driver_id "cFK, restrict, pending"
    bigint vehicle_id "cFK, restrict"
    bigint trailer_id "cFK, restrict, nullable"
    enum status "active / inactive"
    bigint active_vehicle_id UK "generated"
    bigint active_trailer_id UK "generated"
    timestamp deleted_at
  }
  vehicle_documents {
    bigint id PK
    bigint company_id FK
    bigint vehicle_id "cFK, cascade"
    bigint document_type_id FK "restrict"
    bigint file_id "cFK, restrict"
    date file_date
    date expiry_date
    timestamp deleted_at
  }
  trailer_documents {
    bigint id PK
    bigint company_id FK
    bigint trailer_id "cFK, cascade"
    bigint document_type_id FK "restrict"
    bigint file_id "cFK, restrict"
    date expiry_date
    timestamp deleted_at
  }
  service_logs {
    bigint id PK
    bigint company_id FK "restrict"
    bigint vehicle_id "cFK, restrict"
    bigint maintenance_schedule_id "cFK, restrict, nullable"
    date service_date
    decimal total_cost
    enum status "completed / pending / cancelled"
    timestamp deleted_at
  }
  service_log_category {
    bigint id PK
    bigint service_log_id FK "cascade"
    bigint maintenance_category_id FK "restrict"
  }
  service_documents {
    bigint id PK
    bigint company_id FK
    bigint service_log_id "cFK, cascade"
    bigint file_id "cFK, restrict"
  }
  maintenance_schedules {
    bigint id PK
    bigint company_id FK "restrict"
    bigint vehicle_id "cFK, restrict, nullable"
    bigint maintenance_category_id FK "restrict"
    enum schedule_type "date / mileage / engine_hours"
    date next_due_date
    enum status "active / paused / completed"
    timestamp deleted_at
  }
  vehicle_types {
    bigint id PK
    string name UK
  }
  fuel_types {
    bigint id PK
    string name UK
  }
  vehicle_groups {
    bigint id PK
    string name UK
  }
  equipment_types {
    bigint id PK
    string name UK
  }
  maintenance_categories {
    bigint id PK
    string name UK
  }
  drivers {
    bigint id PK
  }
  vehicle_types |o--o{ vehicles : ""
  fuel_types |o--o{ vehicles : ""
  vehicle_groups |o--o{ vehicles : ""
  vehicle_groups |o--o{ trailers : ""
  equipment_types ||--o{ trailers : ""
  drivers ||--o{ asset_groups : "primary"
  drivers |o--o{ asset_groups : "co-driver"
  vehicles ||--o{ asset_groups : ""
  trailers |o--o{ asset_groups : ""
  vehicles ||--o{ vehicle_documents : ""
  trailers ||--o{ trailer_documents : ""
  vehicles ||--o{ service_logs : ""
  maintenance_schedules |o--o{ service_logs : "completed by"
  service_logs ||--o{ service_log_category : ""
  maintenance_categories ||--o{ service_log_category : ""
  service_logs ||--o{ service_documents : ""
  vehicles |o--o{ maintenance_schedules : ""
  maintenance_categories ||--o{ maintenance_schedules : ""
```

## Files, folders and documents

```mermaid
erDiagram
  stored_files {
    bigint id PK
    bigint company_id FK "restrict"
    string disk
    string path UK "companies/id/area/yyyy/mm/hash"
    string original_name
    string mime_type
    bigint size_bytes
    char checksum_sha256
    bigint converted_from_file_id "cFK self, restrict"
    bigint uploaded_by_user_id FK "set null"
  }
  folders {
    bigint id PK
    bigint company_id FK
    bigint parent_id "cFK self, restrict"
    bigint parent_key "generated, UK with company and name"
    string name
    string system_key "standard folders"
    bigint driver_id "cFK, restrict, at most one binding"
    bigint vehicle_id "cFK, restrict"
    bigint trailer_id "cFK, restrict"
    bigint trip_id "cFK, restrict"
    timestamp deleted_at
  }
  documents {
    bigint id PK
    bigint company_id FK
    bigint folder_id "cFK, restrict, where filed"
    bigint file_id "cFK, restrict"
    bigint original_file_id "cFK, source image"
    string title
    bigint document_type_id FK "restrict, nullable"
    date expires_on
    bigint driver_id "subject, cFK, restrict"
    bigint vehicle_id "subject"
    bigint trailer_id "subject"
    bigint trip_id "subject"
    bigint trip_event_id "subject"
    bigint fuel_purchase_id "subject"
    bigint expense_id "subject"
    bigint service_log_id "subject"
    bigint inspection_id "subject, CHECK at most one subject"
    timestamp deleted_at
  }
  exports {
    bigint id PK
    bigint company_id FK
    enum type "zip_driver / zip_trip / zip_company / year_end_report ..."
    enum status "queued / running / ready / failed / expired"
    bigint file_id "cFK, restrict"
    timestamp expires_at
  }
  companies {
    bigint id PK
  }
  companies ||--o{ stored_files : "stores"
  stored_files |o--o{ stored_files : "converted from"
  companies ||--o{ folders : ""
  folders |o--o{ folders : "parent"
  folders |o--o{ documents : "filed in"
  stored_files ||--o{ documents : "content"
  companies ||--o{ exports : ""
  stored_files |o--o| exports : "result"
```

## Trips, inspections, events and tracking

```mermaid
erDiagram
  shippers {
    bigint id PK
    bigint company_id FK "restrict"
    string name
    string contact_name
    string phone
    char state_code
    decimal latitude
    decimal longitude
    string google_place_id
    timestamp deleted_at
  }
  trip_number_sequences {
    bigint company_id PK, FK "cascade"
    bigint next_number "locked on use"
  }
  trips {
    bigint id PK
    bigint company_id FK "restrict"
    string trip_number "UK with company_id"
    date trip_date
    timestamp number_confirmed_at
    enum status "draft / planned / assigned / in_progress / completed / cancelled"
    bigint driver_id "cFK, restrict"
    bigint vehicle_id "cFK, restrict"
    bigint trailer_id "cFK, restrict, nullable"
    bigint shipper_id "cFK, restrict, nullable"
    string product
    decimal weight
    decimal load_pay "snapshot"
    timestamp equipment_confirmed_at
    decimal planned_distance_miles
    decimal actual_distance_miles
    decimal paid_miles "payroll basis"
    timestamp started_at
    timestamp completed_at
    string vehicle_unit_no_snapshot
    string trailer_unit_no_snapshot
    bigint active_driver_id UK "generated, one in-progress trip"
    bigint active_vehicle_id UK "generated"
    timestamp deleted_at
  }
  trip_stops {
    bigint id PK
    bigint company_id FK
    bigint trip_id "cFK, cascade"
    smallint sequence "UK with trip_id"
    enum stop_type "start / pickup / drop_off"
    boolean also_pickup
    bigint shipper_id "cFK, restrict"
    decimal latitude
    decimal longitude
    timestamp arrived_at
    timestamp departed_at
  }
  inspection_templates {
    bigint id PK
    string name "UK with version"
    int version
    boolean is_active
  }
  inspection_template_items {
    bigint id PK
    bigint template_id FK "cascade"
    string section
    string label
    boolean is_required
  }
  inspections {
    bigint id PK
    bigint company_id FK
    bigint trip_id "cFK, restrict, nullable"
    bigint driver_id "cFK, restrict"
    bigint vehicle_id "cFK, restrict"
    bigint template_id FK "restrict"
    enum status "in_progress / completed / abandoned"
    enum result "no_defects / defects_found"
  }
  inspection_results {
    bigint id PK
    bigint inspection_id FK "cascade"
    bigint template_item_id FK "restrict"
    string item_label "snapshot"
    enum result "pass / fail / na"
  }
  trip_events {
    bigint id PK
    bigint company_id FK
    bigint trip_id "cFK, restrict"
    bigint driver_id "cFK, restrict"
    enum type "stop / police_stop / weigh_station / accident / breakdown / fuel"
    timestamp occurred_at
    decimal latitude
    decimal longitude
    text reason "CHECK by type"
    boolean ticket_issued "police stop"
    boolean weight_ok "weigh station"
    smallint injuries_count "accident register"
    smallint fatalities_count "accident register"
    boolean hazmat_released "accident register"
    json details
    timestamp voided_at
  }
  fuel_purchases {
    bigint id PK
    bigint company_id FK
    bigint trip_id "cFK, restrict, nullable"
    bigint trip_event_id UK "cFK, restrict"
    bigint driver_id "cFK, restrict"
    bigint vehicle_id "cFK, restrict"
    timestamp purchased_at
    decimal latitude
    decimal longitude
    decimal gallons
    decimal price_per_gallon
    decimal total_amount
    timestamp confirmed_at
  }
  location_pings {
    bigint id PK
    bigint company_id "no FK, high volume"
    bigint trip_id
    bigint driver_id
    bigint vehicle_id
    datetime recorded_at
    decimal latitude
    decimal longitude
  }
  vehicle_last_locations {
    bigint vehicle_id PK "cFK, cascade"
    bigint company_id
    bigint trip_id
    decimal latitude
    decimal longitude
    timestamp recorded_at
  }
  drivers {
    bigint id PK
  }
  vehicles {
    bigint id PK
  }
  trailers {
    bigint id PK
  }
  shippers |o--o{ trips : "ships"
  drivers |o--o{ trips : "assigned"
  vehicles |o--o{ trips : "tractor"
  trailers |o--o{ trips : "trailer"
  trips ||--o{ trip_stops : "route"
  shippers |o--o{ trip_stops : "at"
  inspection_templates ||--o{ inspection_template_items : ""
  inspection_templates ||--o{ inspections : "used"
  trips |o--o{ inspections : ""
  inspections ||--o{ inspection_results : ""
  inspection_template_items ||--o{ inspection_results : ""
  trips ||--o{ trip_events : ""
  trip_events |o--o| fuel_purchases : "fuel stop"
  trips |o--o{ fuel_purchases : ""
  trips |o..o{ location_pings : ""
  vehicles ||--o| vehicle_last_locations : "live"
```

## Money

```mermaid
erDiagram
  driver_pay_rates {
    bigint id PK
    bigint company_id FK
    bigint driver_id "cFK, restrict"
    enum rate_type "per_mile / hourly"
    decimal rate
    date effective_from "UK with driver and type"
    date effective_to
  }
  expenses {
    bigint id PK
    bigint company_id FK
    bigint trip_id "cFK, restrict, nullable"
    bigint vehicle_id "cFK, restrict, nullable"
    enum category "toll / lumper / scale / parking / repair / other"
    decimal amount
    date incurred_on
    timestamp deleted_at
  }
  time_entries {
    bigint id PK
    bigint company_id FK
    bigint driver_id "cFK, restrict"
    bigint trip_id "cFK, restrict, nullable"
    bigint timesheet_id "cFK, restrict, nullable"
    enum entry_type "driving / on_duty_not_driving / break"
    timestamp started_at
    timestamp ended_at
    bigint open_driver_id UK "generated, one running entry"
  }
  timesheets {
    bigint id PK
    bigint company_id FK
    bigint driver_id "cFK, restrict"
    date period_start "UK with driver"
    date period_end
    int regular_minutes
    int overtime_minutes
    int double_overtime_minutes
    enum status "draft / submitted / approved / exported"
  }
  payroll_runs {
    bigint id PK
    bigint company_id FK
    date period_start
    date period_end
    enum status "draft / approved / exported / failed"
  }
  payroll_run_lines {
    bigint id PK
    bigint company_id FK
    bigint payroll_run_id "cFK, cascade"
    bigint driver_id "cFK, restrict, UK with run"
    bigint timesheet_id "cFK, restrict"
    decimal paid_miles
    int hourly_minutes
    int overtime_minutes
    int double_overtime_minutes
    decimal per_mile_rate "snapshot"
    decimal hourly_rate "snapshot"
    decimal mileage_pay
    decimal hourly_pay
    decimal regular_rate
    decimal overtime_premium
    decimal gross_amount
  }
  drivers {
    bigint id PK
  }
  trips {
    bigint id PK
  }
  drivers ||--o{ driver_pay_rates : ""
  drivers ||--o{ time_entries : ""
  trips |o--o{ time_entries : ""
  timesheets |o--o{ time_entries : "covers"
  drivers ||--o{ timesheets : ""
  payroll_runs ||--o{ payroll_run_lines : ""
  drivers ||--o{ payroll_run_lines : ""
  timesheets |o--o| payroll_run_lines : ""
  trips |o--o{ expenses : ""
```

## Communication, integrations and audit

```mermaid
erDiagram
  conversations {
    bigint id PK
    bigint company_id FK
    enum type "direct / group / trip"
    bigint trip_id "cFK, restrict, nullable"
    timestamp last_message_at
  }
  conversation_participants {
    bigint conversation_id PK "cFK, cascade"
    bigint user_id PK "cFK, cascade"
    bigint company_id
    bigint last_read_message_id
  }
  messages {
    bigint id PK
    bigint company_id FK
    bigint conversation_id "cFK, restrict"
    bigint sender_user_id FK "set null"
    text body
    timestamp sent_at
    timestamp deleted_at
  }
  time_off_requests {
    bigint id PK
    bigint company_id FK
    bigint requested_by_user_id "cFK, restrict"
    bigint driver_id "cFK, restrict, nullable"
    date starts_on
    date ends_on
    enum status "pending / approved / rejected / cancelled"
    bigint reviewed_by_user_id FK "set null"
  }
  integration_connections {
    bigint id PK
    bigint company_id FK "UK with provider"
    enum provider "quickbooks_online"
    string external_account_id
    text access_token "encrypted"
    text refresh_token "encrypted"
    enum status "connected / expired / revoked / error"
  }
  external_references {
    bigint id PK
    bigint company_id FK
    string provider
    enum local_type "driver / payroll_run / timesheet"
    bigint local_id "no FK, integrity query"
    string external_id
  }
  integration_sync_logs {
    bigint id PK
    bigint company_id FK
    bigint integration_connection_id "cFK, cascade"
    enum status "success / failed"
  }
  audit_logs {
    bigint id PK
    bigint company_id "no FK, survives purge"
    enum actor_type "user / platform_user / system / stripe"
    bigint actor_id
    bigint support_session_id
    string action
    string subject_type
    bigint subject_id
    json changes "redacted"
    timestamp created_at "append-only"
  }
  platform_support_sessions {
    bigint id PK
    bigint company_id FK "restrict"
    bigint platform_user_id FK "restrict"
    text reason
    enum scope "read / read_write"
    enum status "requested / approved / rejected / expired / active / ended"
    bigint tenant_approved_by_user_id FK "restrict, required to approve"
    bigint platform_approved_by_platform_user_id FK "restrict, not the requester"
    timestamp expires_at
    timestamp ended_at
  }
  users {
    bigint id PK
  }
  platform_users {
    bigint id PK
  }
  conversations ||--o{ conversation_participants : ""
  users ||--o{ conversation_participants : ""
  conversations ||--o{ messages : ""
  users |o--o{ messages : "sent"
  users ||--o{ time_off_requests : "requests"
  integration_connections ||--o{ integration_sync_logs : ""
  platform_users ||--o{ platform_support_sessions : "requests"
  platform_support_sessions |o..o{ audit_logs : "actions"
```

## Foreign keys and delete rules

What the database does when a parent row is hard-deleted. In normal use tenant data is soft-deleted or deactivated, so most of these rules only matter to the retention purge job. `cFK` is a composite tenant FK.

| Domain | Column | References | Null | On delete |
|---|---|---|---|---|
| Platform | `plan_prices.plan_id` | `plans.id` | required | restrict |
| Platform | `plan_features.plan_id` | `plans.id` | required | cascade |
| Platform | `plan_features.feature_key` | `features.key` | required | restrict |
| Platform | `permissions.feature_key` | `features.key` | nullable | restrict |
| Platform | `site_settings.trial_plan_id` | `plans.id` | nullable | restrict |
| Billing | `company_feature_overrides.company_id` | `companies.id` | required | restrict |
| Billing | `company_feature_overrides.feature_key` | `features.key` | required | restrict |
| Billing | `company_feature_overrides.granted_by_platform_user_id` / `revoked_by_platform_user_id` | `platform_users.id` | nullable | set null |
| Billing | `subscriptions.company_id` | `companies.id` | required | restrict |
| Billing | `subscriptions.plan_price_id` | `plan_prices.id` | required | restrict |
| Billing | `subscriptions.actor_user_id` | `users.id` | nullable | set null |
| Billing | `payments.company_id` | `companies.id` | required | restrict |
| Billing | `payments.subscription_id` | `subscriptions.id` | nullable | restrict |
| Billing | `subscription_notification_logs.subscription_id` | `subscriptions.id` | required | cascade |
| Billing | `subscription_notification_logs.company_id` | `companies.id` | required | restrict |
| Billing | `subscription_notification_logs.recipient_user_id` | `users.id` | nullable | set null |
| Accounts | `companies (id, owner_user_id)` | `users (company_id, id)` | nullable | restrict |
| Accounts | `company_settings.company_id` | `companies.id` | required | cascade |
| Accounts | `users.company_id` | `companies.id` | required | restrict |
| Accounts | `company_invitations (company_id, role_id)` | `roles (team_id, id)` | required | cascade |
| Accounts | `company_invitations` cFK `driver_id` | `drivers` | nullable | restrict |
| Accounts | `company_invitations.invited_by_user_id` / `accepted_user_id` | `users.id` | nullable | set null |
| Accounts | `social_accounts.user_id` | `users.id` | required | cascade |
| Accounts | `device_tokens` cFK `user_id` | `users` | required | cascade |
| Authorization | `model_has_roles (team_id, role_id)` | `roles (team_id, id)` | required | cascade |
| Authorization | `model_has_permissions.permission_id` | `permissions.id` | required | cascade |
| Authorization | `role_has_permissions.role_id` / `permission_id` | `roles.id` / `permissions.id` | required | cascade |
| Drivers | `drivers.company_id` | `companies.id` | required | restrict |
| Drivers | `drivers` cFK `user_id` | `users` | nullable | restrict |
| Drivers | `drivers.created_by_user_id` / `decided_by_user_id` | `users.id` | nullable | set null |
| Drivers | `drivers` cFK `photo_file_id` | `stored_files` | nullable | restrict |
| Drivers | `licenses.driver_id` | `drivers.id` | required | restrict (generated-column base) |
| Drivers | `residence_addresses`, `experiences`, `accidents`, `violations`, `forfeitures`, `employment_records`, `driver_documents` `.driver_id` | `drivers.id` | required | cascade |
| Drivers | `driver_documents.*_file_id` | `stored_files.id` | nullable | restrict |
| Drivers | `driver_compliance_documents` cFK `driver_id` | `drivers` | required | cascade |
| Drivers | `driver_compliance_documents` cFK `file_id` | `stored_files` | required | restrict |
| Drivers | `driver_compliance_documents.document_type_id` | `document_types.id` | required | restrict |
| Drivers | `company_document_type.company_id` / `document_type_id` | `companies.id` / `document_types.id` | required | cascade |
| Drivers | `policy_pdfs.company_id` | `companies.id` | required | restrict |
| Drivers | `policy_pdfs` cFK `*_file_id` | `stored_files` | nullable | restrict |
| Fleet | `vehicles.company_id`, `trailers.company_id` | `companies.id` | required | restrict |
| Fleet | `vehicles.vehicle_type_id` / `vehicle_group_id` / `fuel_type_id` | lookups | nullable | restrict |
| Fleet | `trailers.equipment_type_id` | `equipment_types.id` | required | restrict |
| Fleet | `trailers.vehicle_group_id` | `vehicle_groups.id` | nullable | restrict |
| Fleet | `vehicles` / `trailers` cFK `photo_file_id` | `stored_files` | nullable | restrict |
| Fleet | `asset_groups` cFK `driver_id`, `co_driver_id`, `vehicle_id`, `trailer_id` | `drivers` / `vehicles` / `trailers` | per column | restrict |
| Fleet | `vehicle_documents` cFK `vehicle_id`, `trailer_documents` cFK `trailer_id` | `vehicles` / `trailers` | required | cascade |
| Fleet | `vehicle_documents` / `trailer_documents` cFK `file_id` | `stored_files` | required | restrict |
| Fleet | `vehicle_documents` / `trailer_documents` `.document_type_id` | `document_types.id` | required | restrict |
| Maintenance | `service_logs.company_id` | `companies.id` | required | restrict |
| Maintenance | `service_logs` cFK `vehicle_id` | `vehicles` | required | restrict |
| Maintenance | `service_logs` cFK `maintenance_schedule_id` | `maintenance_schedules` | nullable | restrict |
| Maintenance | `service_log_category.service_log_id` | `service_logs.id` | required | cascade |
| Maintenance | `service_log_category.maintenance_category_id` | `maintenance_categories.id` | required | restrict |
| Maintenance | `service_documents` cFK `service_log_id` | `service_logs` | required | cascade |
| Maintenance | `service_documents` cFK `file_id` | `stored_files` | required | restrict |
| Maintenance | `maintenance_schedules.company_id` | `companies.id` | required | restrict |
| Maintenance | `maintenance_schedules` cFK `vehicle_id` | `vehicles` | nullable | restrict |
| Maintenance | `maintenance_schedules.maintenance_category_id` | `maintenance_categories.id` | required | restrict |
| Files | `stored_files.company_id` | `companies.id` | required | restrict |
| Files | `stored_files` cFK `converted_from_file_id` | `stored_files` | nullable | restrict |
| Files | `stored_files.uploaded_by_user_id` | `users.id` | nullable | set null |
| Files | `folders` cFK `parent_id`, `driver_id`, `vehicle_id`, `trailer_id`, `trip_id` | self / entities | nullable | restrict (CHECK) |
| Files | `documents` cFK `folder_id`, `file_id`, `original_file_id` | `folders` / `stored_files` | per column | restrict |
| Files | `documents` cFK subject columns (9) | entities | nullable | restrict (CHECK) |
| Files | `documents.document_type_id` | `document_types.id` | nullable | restrict |
| Files | `exports` cFK `file_id` | `stored_files` | nullable | restrict |
| Files | `exports.requested_by_user_id` | `users.id` | nullable | set null |
| Operations | `shippers.company_id`, `trips.company_id` | `companies.id` | required | restrict |
| Operations | `trip_number_sequences.company_id` | `companies.id` | required | cascade |
| Operations | `trips` cFK `driver_id`, `vehicle_id`, `trailer_id`, `shipper_id` | entities | nullable | restrict |
| Operations | `trip_stops` cFK `trip_id` | `trips` | required | cascade |
| Operations | `trip_stops` cFK `shipper_id` | `shippers` | nullable | restrict |
| Operations | `inspection_template_items.template_id` | `inspection_templates.id` | required | cascade |
| Operations | `inspections` cFK `trip_id`, `driver_id`, `vehicle_id`, `trailer_id` | entities | per column | restrict |
| Operations | `inspections.template_id` | `inspection_templates.id` | required | restrict |
| Operations | `inspection_results.inspection_id` | `inspections.id` | required | cascade |
| Operations | `inspection_results.template_item_id` | `inspection_template_items.id` | required | restrict |
| Operations | `trip_events` cFK `trip_id`, `driver_id`, `vehicle_id` | entities | per column | restrict |
| Operations | `fuel_purchases` cFK `trip_id`, `trip_event_id`, `driver_id`, `vehicle_id` | entities | per column | restrict |
| Operations | `fuel_purchases.fuel_type_id` | `fuel_types.id` | nullable | restrict |
| Operations | `vehicle_last_locations` cFK `vehicle_id` | `vehicles` | required | cascade |
| Money | `driver_pay_rates`, `timesheets` cFK `driver_id` | `drivers` | required | restrict |
| Money | `time_entries` cFK `driver_id`, `trip_id`, `timesheet_id` | entities | per column | restrict |
| Money | `expenses` cFK `trip_id`, `vehicle_id`, `driver_id` | entities | nullable | restrict |
| Money | `payroll_run_lines` cFK `payroll_run_id` | `payroll_runs` | required | cascade |
| Money | `payroll_run_lines` cFK `driver_id`, `timesheet_id` | entities | per column | restrict |
| Communication | `conversations` cFK `trip_id` | `trips` | nullable | restrict |
| Communication | `conversation_participants` cFK `conversation_id`, `user_id` | `conversations` / `users` | required | cascade |
| Communication | `messages` cFK `conversation_id` | `conversations` | required | restrict |
| Communication | `messages.sender_user_id` | `users.id` | nullable | set null |
| Communication | `time_off_requests` cFK `requested_by_user_id`, `driver_id` | `users` / `drivers` | per column | restrict |
| Communication | `time_off_requests.reviewed_by_user_id` | `users.id` | nullable | set null |
| Integrations | `integration_connections.company_id`, `external_references.company_id` | `companies.id` | required | restrict |
| Integrations | `integration_sync_logs` cFK `integration_connection_id` | `integration_connections` | required | cascade |
| Platform | `platform_support_sessions.company_id` / `platform_user_id` | `companies.id` / `platform_users.id` | required | restrict |
| Platform | `platform_support_sessions.tenant_approved_by_user_id` / `platform_approved_by_platform_user_id` | `users.id` / `platform_users.id` | nullable | restrict (CHECK) |
| Platform | `asset_transfers.source_company_id` / `target_company_id` | `companies.id` | required | restrict |
| Platform | `asset_transfers (source_company_id, source_vehicle_id)` / `(target_company_id, target_vehicle_id)` | `vehicles (company_id, id)` | nullable | restrict (CHECK) |
| Platform | `asset_transfers (source_company_id, source_trailer_id)` / `(target_company_id, target_trailer_id)` | `trailers (company_id, id)` | nullable | restrict (CHECK) |
| Platform | `asset_transfers.performed_by_platform_user_id` | `platform_users.id` | required | restrict |
| Platform | `platform_support_sessions.revoked_by_user_id` | `users.id` | nullable | set null |
| Reference | `states.country_id` | `countries.id` | required | cascade |

`*_by_user_id` columns not listed above are actor pointers, all single-column `set null`. The tables with deliberately **no FK** are `location_pings`, `audit_logs`, `external_references.local_id`, and the Spatie `model_id` columns. The reasons are in [architecture/08-database-schema.md](architecture/08-database-schema.md).

## Open items that affect this diagram

| Item | Effect if decided differently |
|---|---|
| Co-driver (still PENDING) | `asset_groups.co_driver_id` removed |
| Multiple loads per trip (decided: one, Q6) | A `trip_loads` child table holding product, weight and pay, only if the client ever needs it |

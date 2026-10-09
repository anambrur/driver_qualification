# Module report: `asset-groups` (driver ↔ vehicle ↔ trailer)

Run date: 2026-10-09. Tests: `tests/Feature/Modules/AssetGroups` (58 tests, all passing, including the follow-up below).
Full suite: 401 passed, 10 failed. The 10 failures are the same as the baseline (`Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4, `ProfileTest` ×5), so nothing regressed.
Larastan on the scope files: no errors.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| AGR-01 | P0 | FIXED | `AssetGroupTenancyTest.php::AGR-01 → it returns 404 when a tenant opens another tenant's group`; `…::it returns 404 and changes nothing when a tenant updates another tenant's group`; `…::it returns 404 and keeps the group when a tenant deletes another tenant's group`; `…::it returns 404 and keeps the group deleted when a tenant restores another tenant's group`; `…::it lists only the tenant's own groups`; `…::it forbids users without asset-groups permissions` (×8 routes); regression guards `…::it still lets a tenant edit, update, delete and restore their own group`, `…::it lets a super-admin see and edit every company's groups` | `AssetGroupController.php`, `routes/web.php` |
| AGR-02 | P0 | FIXED | `AssetGroupTenancyTest.php::AGR-02 → it only offers the tenant's own drivers, vehicles and trailers on the index page`; `…::it only returns the tenant's own vehicles and trailers from the dropdown endpoint` (×2 routes); regression guard `…::it still shows a super-admin every company's active drivers` | `AssetGroupController.php` |
| AGR-03 | P1 | FIXED | `AssetGroupForeignIdTest.php::AGR-03 → it rejects another tenant's id on store` (driver, vehicle, trailer); `…::it rejects another tenant's id on update` (driver, vehicle, trailer); `…::it makes a super-admin pick the driver and trailer from the vehicle's company` (×2); regression guards `…::it accepts the tenant's own driver, vehicle and trailer`, `…::it lets a super-admin create a group for any company` | `AssetGroupController.php` |
| AGR-04 | P1 | FIXED | `AssetGroupXssTest.php::AGR-04 → it escapes user text in every raw column` (3 payloads); `…::it puts the group name in data attributes instead of inline onclick`; `…::it escapes vehicle and trailer details before inserting them into the form` | `AssetGroupController.php`, `resources/views/admin/asset-group/index.blade.php` |
| AGR-05 | P2 | FIXED | `AssetGroupNameUniquenessTest.php::AGR-05 → it lets two tenants use the same group name`; regression guards `…::it still rejects a duplicate name within one company` (active, soft-deleted), `…::it rejects renaming a group to another group's name in the same company, but allows keeping its own`, `…::it checks a super-admin's new group against the vehicle's company` | `AssetGroupController.php` |

New factory: `database/factories/AssetGroupFactory.php`. It has a `forCompany()` state and a `withTrailer()` state, and it creates the driver and trailer in the same company as the vehicle. `app/Models/AssetGroup.php` gained `BelongsTo` return types on its relations so Larastan can resolve `whereHas('vehicle')`. There is no behaviour change in the model.

### What changed

- **Who owns a group.** *(First pass. Superseded by the `company_id` column in the follow-up.)* `asset_groups` had no `company_id` column, so a group belonged to **the company of its vehicle**, and `vehicle_id` is a required foreign key. `scopedGroups()` adds `whereHas('vehicle', company filter)`. Soft-deleted vehicles still count, so a group stays visible after its vehicle is archived. `index` (ajax), `edit`, `update`, `destroy` and `restore` all go through it. A cross-tenant id returns **404**. Super-admins are not filtered. A non-super-admin with no company sees nothing (same as `CompanyFilterTrait`).
- **Dropdowns (AGR-02).** The vehicles, trailers and active drivers on the index page, and both dropdown endpoints, now use `applyCompanyFilter()`. The line that overwrote the company-filtered `$drivers` with every active driver is removed.
- **Foreign ids (AGR-03).** `driver_id`, `vehicle_id` and `trailer_id` now use `Rule::exists(...)->where('company_id', $companyId)`. For a tenant, `$companyId` is their own company. For a super-admin, it is the company of the selected vehicle, so a super-admin can't mix companies inside one group either. `update()` now validates `driver_id` (it didn't before) and saves `$validator->validated()` instead of `$request->all()`.
- **XSS (AGR-04).** Everything concatenated into the raw columns now goes through `e()`: group name, primary/secondary driver name and phone, vehicle/trailer unit no and VIN. The Delete and Restore buttons no longer use `onclick="fn(id, '<addslashes(name)>')"`. They carry `data-action`, `data-id` and `data-name="<e(name)>"`, and the view has one delegated listener per action that calls the existing `deleteAssetGroup` and `restoreAssetGroup`. SweetAlert already shows the name with `text:`, so it stays inert. The vehicle and trailer detail cards in the form (unit no, year/make/model, licence plate, trailer type) were built with template strings passed to `.html()`. They now pass each value through a small `escapeHtml()` helper. A tenant's vehicle data used to reach a super-admin's browser unescaped there.
- **Unique names (AGR-05).** `unique:asset_groups,group_name` was global. It is replaced by a closure rule that checks names within the owning company, soft-deleted groups included (so restoring a group can't create a duplicate). The default name is `GR#<unit_no>`, and unit numbers are per company, so cross-tenant collisions were likely.
- **Authorization.** Every asset-group route now has `permission:asset-groups.<action>`: `view` for index and both dropdown routes, `create` for store, `edit` for edit/update/restore, `delete` for destroy. These permissions already exist in `PermissionSeeder`. The sidebar already hid the menu entry behind `@can('asset-groups.view')`, so the routes now match the menu.

## Out-of-scope changes

- `routes/web.php`: added `->middleware('permission:asset-groups.*')` to the 8 asset-group routes, and nothing else. This file also has uncommitted changes from the `settings` module run. The odd duplicate path `asset-group/asset-group/get-dropdown-data` is kept, because the view calls it by name. Both copies are now gated and scoped.

## Deferred items (questions for the product owner)

No hotspot was deferred. The two follow-up questions were answered on 2026-10-09 and implemented (see "Follow-up" below):

1. ~~Should tenants manage asset groups?~~ **Yes**: `asset-groups` is added to `COMPANY_MODULES`.
2. ~~Should asset groups get their own `company_id`?~~ **Yes**, because the app is multi-tenant.

## Manual steps

1. `php artisan migrate`. It runs two new migrations:
   - `2026_10_09_000400_add_company_id_to_asset_groups_table` adds `asset_groups.company_id` (FK to `companies`, `ON DELETE CASCADE`, the same as `vehicles.company_id`) and fills it from each group's vehicle. It then makes the column `NOT NULL` and adds an index on `(company_id, group_name)`. Every group has a vehicle (`vehicle_id` is a required FK), so the backfill leaves no row empty. Verified on SQLite (up, down, up again in a test). **Not run on MySQL**, so run it on staging first.
   - `2026_10_09_000500_fix_asset_group_primary_driver_names` changes `primary_driver_name` to the driver's first and last name, only on rows where it is exactly that row's `driver_id`. Typed names and other numbers are left alone. Nothing is lost: the id is still in `driver_id`. `down()` does nothing.
2. `php artisan db:seed --class=PermissionSeeder`. This gives the `company` role `asset-groups.view|create|edit|delete`. The seeder only adds; it never removes. Until it runs, tenants get 403 on the asset-groups pages.
3. Optional: run the legacy data check below *before* migrating. Each group's company is set from its vehicle, so any group whose driver or trailer belongs to a different company will show under the vehicle's company.

   ```sql
   SELECT ag.id, ag.group_name, v.company_id AS vehicle_company, d.company_id AS driver_company, t.company_id AS trailer_company
   FROM asset_groups ag
   JOIN vehicles v ON v.id = ag.vehicle_id
   JOIN drivers d ON d.id = ag.driver_id
   LEFT JOIN trailers t ON t.id = ag.trailer_id
   WHERE d.company_id <> v.company_id OR (t.id IS NOT NULL AND t.company_id <> v.company_id);
   ```

## Follow-up (2026-10-09): tenant access, `company_id`, primary driver, Restore

| Item | Result | Test file::test name | Files changed |
|---|---|---|---|
| Tenants manage their own asset groups | DONE | `AssetGroupTenancyTest.php::AGR-01 → it gives the company role every asset-groups permission`; `…::it forbids users without asset-groups permissions` (now a plain user, ×8 routes) | `database/seeders/PermissionSeeder.php` |
| `asset_groups.company_id` | DONE | `AssetGroupCompanyColumnTest.php::company_id → it stores the tenant's company on create`; `…::it stores the vehicle's company when a super-admin creates a group`; `…::it moves the group when a super-admin assigns another company's vehicle and driver`; `…::it scopes tenants by the group's own company_id`; `…::it backfills company_id from the vehicle and makes it required`; `…::it falls back to the vehicle's company when code creates a group without company_id` | migration `2026_10_09_000400`, `AssetGroupController.php`, `app/Models/AssetGroup.php`, `AssetGroupFactory.php` |
| Primary driver stored as an id | FIXED | `AssetGroupPrimaryDriverTest.php::primary driver → it stores the selected driver's name, not whatever the client sends`; `…::it updates the name when another driver is picked`; `…::it requires a driver on update`; `…::it returns only the driver's id and name from edit, so an inactive driver can be shown`; `…::it names the driver select driver_id in the form`; `…::it repairs groups whose primary_driver_name holds the driver id` | migration `2026_10_09_000500`, `AssetGroupController.php`, `resources/views/admin/asset-group/index.blade.php` |
| Restore button never shown / filters ignored | FIXED | `AssetGroupListFiltersTest.php::list filters → it lists only deleted groups, with just a Restore button, when filtering by Deleted`; `…::it leaves deleted groups out of the default list`; `…::it filters by active and inactive status` (×2); `…::it applies the quick search within the tenant's own groups`; `…::it restores a group from the Deleted list` | `AssetGroupController.php` |

How it works:

- **Permissions:** `PermissionSeeder::COMPANY_MODULES` now includes `asset-groups`. The sidebar's `@can('asset-groups.view')` entry under *Fleet Options* appears for tenants once the seeder has run.
- **`company_id`:**
  - Tenant scoping is now a plain `applyCompanyFilter(AssetGroup::query())`. The `whereHas('vehicle')` subquery is gone.
  - Name uniqueness is `Rule::unique('asset_groups', 'group_name')->where('company_id', …)`, which still counts soft-deleted groups.
  - `store` and `update` set `company_id` on the server, never from the request: a tenant's own company, or for a super-admin the selected vehicle's company. The driver and trailer must belong to that same company. So when a super-admin picks another company's vehicle and driver, the group moves with them.
  - `AssetGroup::creating` fills a missing `company_id` from the vehicle, for seeders, tinker or future code that doesn't set it.
- **Primary driver:**
  - The form's select is now `name="driver_id"`, which replaces the hidden input and the misnamed `primary_driver_name` select. Validation errors for it now show under the field (`#driver_id_error`).
  - The server stores the driver's name as `primary_driver_name` from the driver record and ignores any `primary_driver_name` the client sends. `driver_id` is required on update.
  - Editing no longer re-fetches the driver, so the saved phone and email are kept. Picking a different driver still refills them.
  - `edit` returns `driver:{id, first_name, last_name, status}` (no SSN or other personal data). If the group's driver is no longer active, the form adds them to the select as "(inactive)" so the group can still be saved.
- **Filters and Restore:**
  - The server now reads `status` and `search_text`. *Deleted* lists the soft-deleted groups, *Active* and *Inactive* filter on `status`, and the default leaves deleted groups out. The quick search uses `AssetGroup::scopeSearch` (group name, driver names, email, phone), always inside the tenant's own groups.
  - A deleted row shows only **Restore**, because editing or deleting a deleted group would return 404.

## Out-of-scope changes (follow-up)

- `database/seeders/PermissionSeeder.php`: added `'asset-groups'` to `COMPANY_MODULES`. It was not run here.
- `tests/Feature/ComplianceReminderTest.php` (unchanged) creates an `AssetGroup` without `company_id`. The model's `creating` fallback covers that. The test still fails with the same 403 as in the baseline, which is unrelated to asset groups.

## Observations (not hotspots)

- ~~The primary driver select posted the driver's id as `primary_driver_name`.~~ Fixed in the follow-up.
- ~~The Restore button could not be reached.~~ Fixed in the follow-up.
- **The form fetches details from other modules.** It calls `/admin/driver/{id}/details`, `/admin/vehicle/{id}/details` and `/admin/trailer/{id}/details`. Those routes belong to the drivers and fleet-assets modules and have their own permission and tenancy checks.

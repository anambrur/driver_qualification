# Module report: `fleet-assets` (vehicles & trailers)

Run date: 2026-10-10. Tests: `tests/Feature/Modules/FleetAssets` (67 tests, 230 assertions). All pass.

**Full suite:** 841 passed, 10 failed. The baseline before any change was 774 passed, 10 failed, and the same 10 tests fail in both runs: `Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4 and `ProfileTest` ×5. Nothing regressed.

**Larastan** on the scope files, the new migration and the two factories: 1 error, down from 15 before this run. That one is pre-existing and on a line I didn't touch: `VehicleController::getVehicleDetails()` reads `$vehicle->license_plate`, and `vehicles` has no such column. The other 14 were `Relation '…' is not found` errors. They are fixed by adding `BelongsTo`/`HasOne` return types to the `Vehicle` and `Trailer` relations. `documents()` stays untyped: typing it moves the error into `VehicleDocument`/`TrailerDocument`, which belong to `fleet-compliance`.

A previous automated run of this module (`b3cd327`) was reverted in `eb53e04`. This run started again from the reverted code. It does not reuse that commit.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| FLT-01 | P1 | FIXED | `FleetAssetsXssTest.php::FLT-01: the lists escape tenant-controlled data → it escapes vehicle fields and type, group and fuel names in the vehicle list`; `…::it escapes trailer fields and equipment and group names in the trailer list`; `…::it shows a super-admin another tenant's payload escaped too` (vehicles, trailers); `…::FLT-01: the action buttons carry no inline JavaScript → it puts the unit no in an escaped data attribute, not in onclick` (vehicle/trailer × active/deleted row); `…::it wires the buttons with a delegated listener and escapes toast messages` (×2); regression guard `…::it escapes the company name and owner columns` | `VehicleController.php`, `TrailerController.php`, `admin/vehicle/index.blade.php`, `admin/trailer/index.blade.php` |
| FLT-02 | P2 | FIXED | `FleetAssetsUniquenessTest.php::FLT-02: unit no and VIN are unique per company → it lets another tenant use the same unit no and VIN`; `…::it lets another tenant rename an asset to a unit no and VIN used elsewhere`; `…::it lets a super-admin add a unit no another company uses`; `…::it checks the target company when a super-admin moves an asset`; regression guards `…::it still rejects a duplicate unit no or VIN within the company` (×4), `…::it rejects a unit no of the company's own deleted asset with a 422, not a 500`, `…::it still lets an asset keep its own unit no and VIN on update` (all ×vehicles/trailers) | `VehicleController.php`, `TrailerController.php`, migration `2026_10_10_000300_scope_fleet_asset_unique_keys_to_company.php` (new) |
| FLT-03 | P2 | FIXED. The `$fillable` part is not real (the guard test passed on the old code). The cross-company move is fixed with a guard, and a follow-up question is DEFERRED (item 1). | `FleetAssetsUpdateTest.php::FLT-03: a super-admin moving an asset to another company → it refuses to move a vehicle that has company records` (asset group, service log, maintenance schedule); `…::it refuses to move a trailer that is in an asset group`; regression guards `…::FLT-03: update only saves the form fields → it ignores company_id, id, timestamps and deleted_at posted by a tenant` (×2, passed before the fix), `…::it refuses another tenant's asset` (×2), `…::it saves the optional fields the form sends`, `…::it still moves an asset that has no company records` (×2), `…::it still lets a super-admin edit an asset with records when the company stays the same` | `VehicleController.php`, `TrailerController.php` |
| FLT-04 | P3 | FIXED | `FleetAssetsListFiltersTest.php::FLT-04: deleted assets can be listed and restored → it lists deleted assets under the Deleted filter with a Restore button`; `…::it keeps deleted assets out of the default and Active lists` (×4); regression guards `…::it does not list another tenant's deleted assets`, `…::it restores the tenant's own asset`, `…::it refuses to restore another tenant's asset` (all ×vehicles/trailers) | `VehicleController.php`, `TrailerController.php` |
| FLT-05 (new) | P3 | FIXED | `FleetAssetsListFiltersTest.php::FLT-05: the quick search and type filter → it filters by the quick search text` (unit no, VIN, make, model × vehicles/trailers); `…::it keeps the quick search inside the tenant`; `…::it filters vehicles by type`; `…::it filters trailers by equipment type`; regression guards `…::it lists everything when the filters are empty`, `it requires the vehicle and trailer permissions` | `VehicleController.php`, `TrailerController.php` |

Test fixtures are in `tests/Feature/Modules/FleetAssets/FleetAssets.php`. The module section lists no factories to add. The existing `VehicleFactory` and `TrailerFactory` are used as they are, apart from a VIN length fix (see "Out-of-scope changes").

### What changed

- **FLT-01: stored XSS.**
  - **List columns.** Both lists now escape the following with `e()`:
    - Vehicle list (`vehicle_info`, `type_group`, `fuel_odometer`): unit no, year, make, model and VIN; vehicle type, group and fuel type names.
    - Trailer list (`trailer_info`, `equipment_group`): unit no, year, make, model and VIN; equipment type and group names.
  - **Columns already safe.** `company_name` and `owned_by` were never raw: Yajra escapes non-raw columns by default, with `escape => '*'`. A test guards this.
  - **Action buttons.** The Edit/Delete/Restore buttons used `onclick="deleteVehicle(id, '<addslashes(unit_no)>')"`. `addslashes()` doesn't stop `&#39;` (which the HTML parser decodes into a quote inside the JavaScript) or a `"` (which closes the attribute).
    - The buttons now carry `data-action`, `data-id` and `data-unit-no="<e(unit_no)>"`.
    - A delegated listener on the table (`.js-vehicle-action` / `.js-trailer-action`) calls the existing `editX`/`deleteX`/`restoreX` functions.
    - The SweetAlert confirmations already used `text:`, not `html:`.
  - **Client side.**
    - Toasts now run the message through a new `escapeHtml()`.
    - Validation errors are set with `.text()` instead of `.html()`.
- **FLT-02: per-company uniqueness.**
  - **Validation.** The `unique:vehicles,unit_no` / `unique:vehicles,vin` rules (and the trailer ones) are now `Rule::unique(...)->where('company_id', <target company>)->ignore(<own id>)`.
    - For a tenant, the target company is the tenant's own company.
    - For a super-admin, it is the company they picked, so moving an asset into a company that already has that unit no is refused.
  - **Database.** The new migration replaces the global unique indexes on `vehicles` and `trailers` (`unit_no`, `vin`) with `(company_id, unit_no)` and `(company_id, vin)`. Without it, the second tenant's insert would have failed in the database with a 500.
  - **Deleted assets.** The rule still counts the company's own soft-deleted rows, as the index does. Re-adding a deleted unit no gives a 422, not a 500. Restoring the deleted asset is the way back.
- **FLT-03: update and company moves.**
  - **`$fillable`.** It already kept out `id`, timestamps and `deleted_at`, and a tenant's `company_id` is overwritten with the asset's own. So the "sensitive fields" half of the hotspot isn't real; the guard test passed on the old code.
  - **Saving validated data.** `update()` (both controllers) and `TrailerController::store()` now save `$validator->validated()` instead of `$request->all()`.
  - **Company moves.**
    - Asset groups, service logs and maintenance schedules have their own `company_id`. When a super-admin moved a vehicle or trailer to another company, those records stayed in the old company while pointing at the other company's asset.
    - So the old tenant kept a group, log or schedule built on the new owner's vehicle, and saw its unit no, VIN and odometer through it.
    - **Guard:** `company_id` now fails validation (422) when the asset has any such record, soft-deleted ones included. For trailers this means asset groups only.
    - Assets without such records still move. Their documents (`vehicle_documents`/`trailer_documents`, which have no `company_id`) follow the asset.
    - Whether a move should instead take the history along is deferred (item 1).
- **FLT-04: deleted assets.**
  - **Before.** The list query never included soft-deleted rows, so the page's "Deleted" filter showed nothing and Restore never rendered.
  - **Now.**
    - `status=deleted` lists only the tenant's deleted assets (`onlyTrashed()` after the company filter).
    - A deleted row shows only Restore. Edit was dropped from it because `edit()` returns 404 for a deleted asset.
    - "All Status" and "Active" still list only live assets (see item 2).
- **FLT-05 (new): quick search and type filter.**
  - **Before.** The pages send `search_text` and `vehicle_type_id` / `equipment_types_id`, but the controllers ignored both, so "Quick Search" and the type filter did nothing.
  - **Now.** They use the models' existing `search()` scope (unit no, VIN, make, model, year) and a `where` on the type column. Both are applied on top of the company filter.

## Out-of-scope changes

- **New migration** `database/migrations/2026_10_10_000300_scope_fleet_asset_unique_keys_to_company.php` (FLT-02).
- **`database/factories/VehicleFactory.php` and `TrailerFactory.php`.** The VIN pattern produced 16 characters, while the controllers require exactly 17 (`size:17`). As a result, any test that posted a factory vehicle back through the edit form failed validation. The pattern now has one more digit. No other factory change.

No routes, shared traits, middleware or config were changed. The `permission:vehicles.*|trailers.*` route middleware was already in place. A test guards it: `FleetAssetsListFiltersTest::it requires the vehicle and trailer permissions`.

## Deferred items (questions for the product owner)

1. **FLT-03: what should happen when a super-admin moves a vehicle or trailer to another company?**
   - **Now:** the move is refused while the asset has asset groups, service logs or maintenance schedules in its current company.
   - **Option A:** move them along. Set their `company_id` to the new company. The asset group's driver belongs to the old company, so the group would probably have to be closed rather than moved.
   - **Option B:** keep the history with the old company and give the new company a fresh start. The old company's records would then need to stop showing the asset's current data.
   - **Option C:** never allow moves. Remove the company picker from the super-admin's edit form.
   - Which one is intended?
2. **FLT-04: should "All Status" include deleted assets?**
   - The Status filter has "All Status", "Active" and "Deleted". I made "All Status" behave as before: live assets only, with deleted ones only under "Deleted". That way, deleted assets don't reappear in the default list or in the "Total: N" count.
   - Should "All Status" list both, with the red "Deleted" badge the status column already supports?
3. **FLT-02: should VIN be unique per company, or global?**
   - I made it per company, like the unit no. Reasons:
     - A truck can be sold or leased on from one carrier to another, and both may keep it in their records.
     - A global check tells one tenant about another tenant's fleet.
   - If a VIN should only ever exist once in the whole system, say so. The per-company index can be changed back for `vin` only, and the error message should then not reveal the other company.

## Manual steps

1. **Back up the database first.** Then run `php artisan migrate`, which runs `2026_10_10_000300_scope_fleet_asset_unique_keys_to_company`.
   - On `vehicles` and `trailers` it adds the composite unique indexes `(company_id, unit_no)` and `(company_id, vin)`, then drops `*_unit_no_unique` and `*_vin_unique`.
   - Existing data can't conflict, because the old indexes were stricter.
   - `down()` restores the global indexes. It fails once two companies share a unit no or VIN.
   - It was tested on SQLite only. Run it on staging (MySQL) first.
2. No config change is needed.

## Observations (not hotspots)

- **`getVehicleDetails()` returns `license_plate`**, which `vehicles` doesn't have, so it is always `null`. This is the remaining Larastan error.
- **The controller memoizes the tenant.** `CompanyFilterTrait` caches the company per controller instance, and Laravel caches the controller on the route. Within one PHP process, a second request by a different user reuses the first user's company. Under php-fpm every request is a new process, so this only affects tests that switch users between requests. The restore tests use one actor per test for this reason. It would become a real cross-tenant bug if the app ever ran under Octane, Swoole or RoadRunner.
- **The VIN rule is only `size:17`.** There is no character check (VINs exclude I, O and Q) and no check digit. That is unchanged.
- **Deleting a vehicle doesn't touch its asset group.** The group stays active and still points at the deleted vehicle. This is the existing behavior and wasn't changed.

## Decision follow-up: PO-08

Decided 2026-10-11: option A. A super-admin's move is no longer refused.

- `VehicleController::update()`: in the same transaction, the vehicle's service logs and maintenance schedules (trashed included) get the new `company_id`. Its asset groups stay with the old company, whose driver they name. They are ended the way `AssetGroupController` ends a group (`status = inactive` and soft-deleted), so the new company never sees the old driver through `$vehicle->assetGroups` (dashboard, reminders). Vehicle documents point at the vehicle and follow it.
- `TrailerController::update()`: the trailer is taken out of the old company's asset groups (`trailer_id = null`). The groups keep their vehicle and driver and stay active, because a group can't lose its required vehicle.
- Unit no / VIN are still checked as unique in the target company.
- Tests: `FleetAssetsUpdateTest.php`. The three "refuses to move" tests were replaced by "moves … (decided PO-08)", "ends the vehicle's asset groups …", "takes a moved trailer out …" and "still refuses a move when the unit no is taken".
- Out-of-scope: asset-groups rows are written (no asset-groups code changed). Known leftover: the old company can still restore its ended group from the "Deleted" filter, and it would then point at the moved vehicle. `AssetGroupController::restore()` doesn't check the vehicle's company.

## Decision follow-up: PO-09

Decided 2026-10-11: option A. "All Status" (empty or `all`) uses `withTrashed()`, "Active" shows live assets only, and "Deleted" uses `onlyTrashed()`. Deleted rows keep their badge and the Restore button. `recordsTotal` counts both. Tenant scoping is applied before `withTrashed()`.

Tests: `FleetAssetsListFiltersTest.php` (the default-list test was split into "keeps deleted assets out of the Active list" and "lists live and deleted assets under All Status … (decided PO-09)").

## Decision follow-up: PO-10

Decided 2026-10-11: option A. VIN stays unique per company. DISMISSED. Guard test renamed: `FleetAssetsUniquenessTest.php` → `lets another tenant use the same unit no and VIN (VIN per company, decided PO-10, 2026-10-11)`.

## Decision follow-up: PO-22 (fleet-assets items)

- `getVehicleDetails()` no longer returns `license_plate`.
- VIN also has `not_regex:/[IOQ]/i` on store and update (vehicles and trailers), with the message "A VIN never contains the letters I, O or Q." The Vehicle/Trailer factories and `FleetAssets::vin()` now generate VINs without I/O/Q, because random letters made tests flaky.
- Deleting a vehicle sets its active asset group to `inactive`.
- Tests: `FleetAssetsDecisionsTest.php`.

## Decision follow-up: PO-22 (models / Larastan)

Relations on `Driver`, `Vehicle`, `Trailer`, `VehicleDocument`, `TrailerDocument` and `Subscription` now have `BelongsTo` / `HasMany` / `HasOne` return types with generics. `Subscription` got `@property Carbon|null` for its datetime columns. `vendor/bin/phpstan analyse` on those six files: no errors (it reported 2 before).

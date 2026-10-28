# 02 · Requirement matrix

This matrix checks every requirement against the code, not against whether a table or screen exists. It draws on three sources:

- **Client:** `docs/Usecases - DOTDocHub.pdf` (Harinder Garacha, 7 March 2023). This is the primary source of intended behaviour.
- **Gap report:** `docs/DriverFilesHub-Requirements-Gap-Report.pdf` (26 September 2026). This is the comparison document; its numbering is used below.
- **Code:** commit `d1d4d8d`.

## How to read the tables

**Verified status**

| Status | Meaning |
|---|---|
| **Complete** | The business behaviour works today |
| **Partial** | Some of the behaviour exists |
| **Missing** | Nothing implements it |
| **Unclear** | The requirement itself needs clarifying |

**Phase:** the delivery phase from [13-implementation-roadmap.md](13-implementation-roadmap.md). Phase 0 is the tenancy foundation that everything else depends on.

**Feature:** the key in the feature registry ([05-plans-features-entitlements.md](05-plans-features-entitlements.md)). "core" means the feature is always available to any subscribed company.

## Summary

| | Gap report | Verified |
|---|---|---|
| Complete / Available | 11 | 9 |
| Partial | 7 | 4 |
| Missing / Not available | 34 | 39 |
| **Total** | **52** | **52** |

After Phase 0 (21 Oct 2026): 1.7 is Complete (roles per company). After Phase 1 (23 Oct 2026): 1.8, 2.2, 2.3, 2.4 and 3.5 are Complete; 1.4, 2.1 and 2.5 are Partial until their Phase 2–3 parts land. After Phase 2 (28 Oct 2026): 2.1, 2.5, 4.1–4.5, 5.1, 6.3 and 9.2 are Complete; 4.6, 5.2, 5.3, 6.1 and 7.1–7.6 are Complete on the web, with the driver app in Phase 3. The table above still shows the state at `d1d4d8d`.

The differences come from the corrections in section 12. Eight further client items that the gap report merged or left out are listed in section 11.

---

## 1. Platform and account

| # | Requirement | Gap | Verified | Code today | DB changes | Feature | Depends on | Phase | Risks / notes |
|---|---|---|---|---|---|---|---|---|---|
| 1.1 | Web / admin application | Available | **Complete** | Blade admin under `/admin` (`routes/web.php:214-462`) | Split into a platform area and a tenant area | core | — | 0 | Done in Phase 0: the super-admin moved to `/platform` (doc 04) |
| 1.2 | Mobile application | Not avail. | **Missing** | No `routes/api.php`, no Sanctum | `personal_access_tokens`, `device_tokens` | `mobile_app` | 0, 2 | 3 | Mobile app development is a separate workstream |
| 1.3 | Register with email | Available | **Complete** | `RegisteredUserController.php:36-87`. Verification is required (PO-15). | Registration provisions a company: roles, owner, settings | core | — | 0 | Done in Phase 0: registration asks for the company name. Since 23 Oct 2026 it also asks for the time zone (14 Z1). |
| 1.4 | Register / login with Google and Facebook (web and mobile) | Not avail. | **Partial** (P1, 23 Oct 2026: web done; mobile and Apple in Phase 3) | Login and register buttons are commented-out placeholders | `social_accounts` | platform capability | 0 | 1 | [L] Apple sign-in is needed on iOS if other social logins are offered (App Store rule 4.8). Facebook Login needs app review. |
| 1.5 | Log in | Available (says 2FA) | **Complete** | `LoginRequest.php:53-82`, `EnsureUserIsActive` | `users.company_id`; check company status at login | core | 0 | 0 | **There is no 2FA**; the gap report is wrong. 2FA for platform staff was recommended [R]; the product owner decided against it (23 Oct 2026, 14 §2). |
| 1.6 | Edit company info | Available | **Complete** | `CompanyController.php` edit/update with `ensureCanManageCompany` (`:550-563`) | `companies.owner_user_id`; contact email separated from login email | core | 0 | 0 | Done in Phase 0: contact email no longer synced from the owner's login (decided D3b) |
| 1.7 | Set permissions per role | Available | **Complete** (P0, 21 Oct 2026) | `RoleController.php`, super-admin only; roles are global | Spatie teams, roles per company | `custom_roles` | 0 | 0 | Done in Phase 0: roles are per company (Spatie teams), so editing one affects only that company |
| 1.8 | Set permissions per individual user | Partial | **Complete** (P1, 23 Oct 2026) | Only Spatie's direct-permission table exists; companies can't manage users at all | `model_has_permissions` with `team_id` | `custom_roles` | 1.7 | 0 | Escalation rules (doc 06) |

## 2. Folders and documents

| # | Requirement | Gap | Verified | Code today | DB changes | Feature | Depends on | Phase | Risks / notes |
|---|---|---|---|---|---|---|---|---|---|
| 2.1 | Standard set of folders | Partial | **Complete** (P1, 23 Oct 2026: driver, vehicle and trailer; P2-2, 26 Oct 2026: trip) | Fixed document types per driver, vehicle and trailer act as slots (`DocumentTypeSeeder.php`) | System folders per entity in `folders` (`system_key`) | `document_folders` | 0 | 1 | Standard folders per entity: DQ file, vehicle, trailer, trip (decided Q8) |
| 2.2 | Add, create, edit and delete folders | Not avail. | **Complete** (P1, 23 Oct 2026) | — | `folders` | `document_folders` | 2.1 | 1 | Nesting depth limit [R] |
| 2.3 | Upload documents to a specific folder | Available | **Complete** (P1, 23 Oct 2026) | Typed compliance uploads with expiry (`DriverDocumentUploadController`, `DocumentUploadController`) | `stored_files`, `documents.folder_id` | `document_folders` | 2.1 | 1 | Works for typed compliance slots only, not for arbitrary folders |
| 2.4 | Uploaded picture converts to PDF automatically | Not avail. | **Complete** (P1, 23 Oct 2026) | `dompdf` is installed but unused | `stored_files.converted_from_file_id` | platform capability | 2.3 | 1 | Folder uploads converted; original kept (decided Q8) |
| 2.5 | ZIP download per trip, per driver, and for the whole company | Not avail. | **Complete** (P1, 23 Oct 2026: driver and company; P2-6, 28 Oct 2026: trip, and trips in the company ZIP) | Files download one at a time | `exports` | `zip_export` | 2.3, 4.1 | 1 (driver and company), 2 (trip) | Large companies need a queued job and a size cap. A ZIP contains everything attached to the entity (decided Q8). |

## 3. Drivers, tractors and trailers

| # | Requirement | Gap | Verified | Code today | DB changes | Feature | Depends on | Phase | Risks / notes |
|---|---|---|---|---|---|---|---|---|---|
| 3.1 | Add a new driver | Available | **Complete** | `DriverController` store and wizard; public application | `drivers.created_by_user_id` (no cascade), `drivers.user_id` (login), split status | `drivers`, `driver_applications` | 0 | 0 | `max_drivers` limit; only active hired drivers count (decided Q4) |
| 3.2 | Update any driver's data | Available | **Complete** | `DriverController::update`, `DriverCrudService` | Same as 3.1. Drop `licenses.repeat_license_number`; add a current-licence flag. | `drivers` | 0 | 0 | Phase 0: licences are upserted with `is_current`; the other child rows are still deleted and re-inserted (`DriverCrudService.php:65-80`), which loses row ids [R] |
| 3.3 | Add and delete tractors by type | Available | **Complete** | `VehicleController`; `vehicle_type_id` | Plate fields (3.5) | `fleet` | — | 0 | `max_vehicles` |
| 3.4 | Add and delete trailers (year, make, model) | Available | **Complete** | `TrailerController` | Rename `equipment_types_id` to `equipment_type_id` (done in Phase 0) | `fleet` | — | 0 | `max_trailers` |
| 3.5 | Licence plate number and picture for tractors and trailers | Not avail. | **Complete** (P1, 23 Oct 2026) | Not stored. `license_plate` appears only in unused JS (`asset-group/index.blade.php:952,998`). | `plate_number`, `plate_state_code`, `photo_file_id` on vehicles and trailers | `fleet` | `stored_files` | 1 | — |

## 4. Trip and load management

| # | Requirement | Gap | Verified | Code today | DB changes | Feature | Depends on | Phase | Risks / notes |
|---|---|---|---|---|---|---|---|---|---|
| 4.1 | Create a trip / edit trip details | Not avail. | **Complete** (P2-2, 26 Oct 2026; assignment P2-3) | — | `trips`, `trip_stops` | `trips` | 0 | 2 | One load per trip (decided Q6) |
| 4.2 | Automatic trip number and date, confirmed by the owner | Not avail. | **Complete** (P2-2, 26 Oct 2026) | — | `trip_number_sequences`; `trips.number_confirmed_at` / `_by` | `trips` | 4.1 | 2 | The number is unique per company and assigned under a row lock |
| 4.3 | Trip details (product, weight) | Not avail. | **Complete** (P2-2, 26 Oct 2026) | — | `trips.product`, `weight`, `weight_unit` | `trips` | 4.1 | 2 | "Load type" in 8.6 = product (decided C8) |
| 4.4 | Add shipper information and save shippers for reuse | Not avail. | **Complete** (P2-1, 26 Oct 2026; used on trips from P2-2) | — | `shippers` | `trips` | 0 | 2 | — |
| 4.5 | Assign a driver to a trip / assign a load to a driver | Not avail. | **Complete** (P2-3, 26 Oct 2026) | Defaults from the driver's active asset group | `trips.driver_id`, `vehicle_id`, `trailer_id` | `trips` | 4.1 | 2 | These are two client bullets for one action (section 13) |
| 4.6 | Confirm the assigned tractor and trailer are correct | Not avail. | **Complete on the web** (P2-3, 26 Oct 2026: staff confirm for the driver; the driver in the app is Phase 3) | — | `trips.equipment_confirmed_at` / `_by_user_id` | `trips` | 4.5 | 2 | Done by the driver in the app |

## 5. Pre-trip inspection

| # | Requirement | Gap | Verified | Code today | DB changes | Feature | Depends on | Phase | Risks / notes |
|---|---|---|---|---|---|---|---|---|---|
| 5.1 | Fill out a pre-trip checklist | Not avail. | **Complete** (P2-4, 28 Oct 2026: staff record it for the driver; the driver in the app is Phase 3) | — | `inspection_templates`, `inspection_template_items`, `inspections`, `inspection_results` | `pre_trip_inspections` | 4.1 | 2 | A pre-trip checklist only; not a DVIR (decided C10) |
| 5.2 | Pre-trip inspection on one screen | Not avail. | **Complete on the web** (P2-4, 28 Oct 2026; the app is Phase 3) | — | (UI) | `pre_trip_inspections` | 5.1 | 2–3 | — |
| 5.3 | See checklist progress until finished | Not avail. | **Complete on the web** (P2-4, 28 Oct 2026; the app is Phase 3) | — | Progress = answered results ÷ template items | `pre_trip_inspections` | 5.1 | 2–3 | — |

## 6. Maps, driving and tracking

| # | Requirement | Gap | Verified | Code today | DB changes | Feature | Depends on | Phase | Risks / notes |
|---|---|---|---|---|---|---|---|---|---|
| 6.1 | Choose start and drop-off from a map (Google or Apple Maps) | Not avail. | **Complete on the web** (P2-2, 26 Oct 2026; the app is Phase 3) | — | `trip_stops` with lat/lng, `place_source`, `place_ref` | `trips` | 4.1 | 2 (web), 3 (app) | Free map stack (decided 23 Oct 2026): Leaflet + OpenStreetMap tiles, server-side geocoder. [L] A tile provider once traffic outgrows the public OSM tile policy |
| 6.2 | Use current location as the start | Not avail. | **Missing** | — | `trip_stops` (`stop_type = start`) | `mobile_app` | 1.2 | 3 | — |
| 6.3 | Optional pickup between start and drop-off; not required when it equals the start | Not avail. | **Complete** (P2-2, 26 Oct 2026) | — | `trip_stops.also_pickup` on the start stop | `trips` | 4.1 | 2 | — |
| 6.4 | "Start driving!" button, "Trip Started" screen, opens the trip folder | Not avail. | **Missing** | — | `trips.status`, `started_at`; trip system folder | `mobile_app` | 4.1, 2.1 | 3 | — |
| 6.5 | Miles travelled and distance to destination | Not avail. | **Missing** | — | `trips.actual_distance_miles`, `planned_distance_miles` | `gps_tracking` | 6.7 | 3 | Remaining distance is computed in the app from the Maps API |
| 6.6 | Continue a trip that was exited | Not avail. | **Missing** | — | The trip stays `in_progress`; the app reloads it | `mobile_app` | 6.4 | 3 | — |
| 6.7 | Live location of trucks and trailers | Not avail. | **Missing** | — | `location_pings`, `vehicle_last_locations` | `gps_tracking` | 1.2 | 3 | A trailer's location comes from the truck on its active trip (decided C5). [L] Notice and consent for employee tracking. |

## 7. Trip events and stops

| # | Requirement | Gap | Verified | Code today | DB changes | Feature | Depends on | Phase | Risks / notes |
|---|---|---|---|---|---|---|---|---|---|
| 7.1 | "Add Stop" with a reason | Not avail. | **Complete on the web** (P2-5, 28 Oct 2026; camera uploads from the app are Phase 3) | — | `trip_events` (`type = stop`, `reason`) | `trip_events` | 4.1 | 2 | — |
| 7.2 | Police stop: ticket yes/no, upload the ticket, or give a reason | Not avail. | **Complete on the web** (P2-5, 28 Oct 2026; camera uploads from the app are Phase 3) | — | `trip_events.ticket_issued` + CHECK; documents attached to the event | `trip_events` | 7.1 | 2 | — |
| 7.3 | Weigh station: weight OK yes/no, upload documents if no | Not avail. | **Complete on the web** (P2-5, 28 Oct 2026; camera uploads from the app are Phase 3) | — | `trip_events.weight_ok` + CHECK | `trip_events` | 7.1 | 2 | — |
| 7.4 | Accident: upload licence, insurance, photos of both vehicles | Partial | **Complete on the web** (P2-5, 28 Oct 2026; camera uploads from the app are Phase 3) | `accidents` holds the applicant's past history; nothing records live events | `trip_events` (`type = accident`) + documents | `trip_events` | 7.1 | 2 | Accident register report generated from accident events (decided C9) |
| 7.5 | Breakdown event | Not avail. | **Complete on the web** (P2-5, 28 Oct 2026; camera uploads from the app are Phase 3) | — | `trip_events` (`type = breakdown`) | `trip_events` | 7.1 | 2 | Could later link to maintenance [R] |
| 7.6 | "Add Fuel Stop": location, price, documents, "Done" screen | Partial | **Complete on the web** (P2-5, 28 Oct 2026; camera uploads from the app are Phase 3) | Only the `fuel_types` lookup exists | `fuel_purchases` (+ `trip_event_id`) | `trip_events` | 7.1 | 2 | — |

## 8. Pay, expenses and reports

| # | Requirement | Gap | Verified | Code today | DB changes | Feature | Depends on | Phase | Risks / notes |
|---|---|---|---|---|---|---|---|---|---|
| 8.1 | Enter pay for each load | Not avail. | **Missing** | — | `trips.load_pay`, `currency` | `driver_pay` | 4.1 | 4 | Snapshot value on the trip |
| 8.2 | Driver pay per mile and hourly rate | Not avail. | **Missing** | — | `driver_pay_rates` (effective-dated) | `driver_pay` | 3.1 | 4 | Mileage pay plus hourly pay on non-driving time (decided C1, engineering decision) |
| 8.3 | Daily net profit or loss (pay minus expenses) | Partial | **Missing** | No profit code; the old page was removed (ACL-04) | `expenses`; a report query over trips, fuel, service logs, expenses and payroll | `profit_loss` | 8.1, 8.2, 7.6 | 4 | Variable costs only (decided C2); days follow the company time zone |
| 8.4 | Timesheets: hours, overtime, double overtime | Not avail. | **Missing** | — | `time_entries`, `timesheets` | `timesheets` | 3.1 | 4 | Daily thresholds, configurable; default 8 h / 12 h (decided C3). [L] Exemptions per driver; not hours-of-service records (Part 395). |
| 8.5 | Reporting access | Partial | **Partial** | Compliance dashboards, home dashboard | `exports`; report queries | `profit_loss`, `year_end_reports` | 8.x | 4 | — |
| 8.6 | Year-end download: driver travel history, load type, trip no., tractor, trailer, hours, miles | Not avail. | **Missing** | — | `exports`; trip snapshots (unit numbers) | `year_end_reports` | 4.x, 8.4 | 4 | Load type = product (decided C8) |

## 9. Dashboard

| # | Requirement | Gap | Verified | Code today | DB changes | Feature | Depends on | Phase | Risks / notes |
|---|---|---|---|---|---|---|---|---|---|
| 9.1 | Dashboard overview | Available | **Complete** | `DashboardController.php:20-144`, tenant-filtered | Respect entitlements | core | 0 | 0 | — |
| 9.2 | Loads on the road and loads pending pickup | Not avail. | **Complete** (P2-6, 28 Oct 2026) | — | Index `trips(company_id, status)`; pickup-stop state | `trips` | 4.1 | 2 | "Pending pickup" means assigned but the pickup stop not yet departed [R] |

## 10. Integrations and communication

| # | Requirement | Gap | Verified | Code today | DB changes | Feature | Depends on | Phase | Risks / notes |
|---|---|---|---|---|---|---|---|---|---|
| 10.1 | Link QuickBooks and send payroll data | Not avail. | **Missing** | — | `integration_connections`, `external_references`, `integration_sync_logs`, `payroll_runs` | `quickbooks` | 8.2, 8.4 | 4 | QuickBooks Online: Employees + TimeActivity, plus a payroll summary export (decided C4). [A] Pay data is behind Intuit's gated Payroll API. |
| 10.2 | In-app messages | Not avail. | **Missing** | — | `conversations`, `conversation_participants`, `messages` | `messaging` | 1.2 | 3 | — |
| 10.3 | Notify drivers of new messages | Partial | **Missing** | Compliance emails only | `device_tokens`; `notifications` (exists) | `messaging` | 10.2 | 3 | Push notifications need an FCM/APNs setup |
| 10.4 | Time-off requests | Not avail. | **Missing** | — | `time_off_requests` | `time_off` | 0 | 4 | Drivers and staff submit; Owner or Admin approves (decided C6) |

---

## 11. Client items the gap report merged or left out

| # | Client text (abridged) | Verified | Covered by | Notes |
|---|---|---|---|---|
| X1 | "should not be required to enter the pickup destination if it is the same as starting destination" | Missing | 6.3 (`also_pickup`) | — |
| X2 | "taken to the trip folder for the current trip under their profile" | Missing | 6.4, 2.1 | — |
| X3 | "see later on by the current location where the driver stopped for fuel" | Missing | 7.6 (`fuel_purchases` lat/lng) | — |
| X4 | "prompted to upload documents and click confirm" / "Done screen" (fuel) | Missing | 7.6 (`confirmed_at`) | — |
| X5 | "input gas purchase price into the add fuel area" | Missing | 7.6 (`price_per_gallon`, `total_amount`) | The gap report merged this into 7.6 |
| X6 | Blank bullet after "miles traveled and the distance" | **Unclear** | — | Investigated: the bullet holds only a zero-width space; no hidden text. Ask the client (still open). |
| X7 | Actor list is "User / Owner" only, yet the owner performs driving tasks | Resolved | Drivers log in (decision log) | — |
| X8 | Year-end report includes "load type" | Resolved | 4.3, 8.6 | Load type = product (decided C8) |

## 12. Corrections to the gap report

| Gap report says | Correction | Evidence |
|---|---|---|
| Login includes optional 2FA | No 2FA exists | `UserSuspendRoutesTest.php:63-71` |
| Profit page layout exists | It was removed (ACL-04) | `RouteGuardMatrixTest.php:210-229` |
| Permissions per role: Available | Partial: super-admin only, global roles | `RoleManagementTest.php:63-72` |
| Permissions per user: Partial | Missing: no UI, and companies can't manage users | `UserAuthorizationTest.php:57-75` |
| Upload to a specific folder: Available | Partial: typed compliance slots only | `DriverDocumentUploadController.php` |
| Accident and fuel stop: Partial | Missing: they rest on unrelated tables | `accidents` migration, `fuel_types` |
| Notify drivers: Partial | Missing | — |

## 13. Overlapping, contradictory and ambiguous requirements

| Topic | Issue | Handling |
|---|---|---|
| Trip vs load | "assign a Driver to a Trip" and "assign a load to a driver"; "pay for the load"; "loads on the road" | One load per trip (decided Q6) |
| Who does what | Every bullet says "owner", including driving tasks | Drivers log in (decision log). The owner may also be a driver (owner-operator). |
| Time off | "Owner should be able to put in time off request" | Drivers and staff submit; an Owner or Administrator approves (decided C6) |
| Pay basis | Pay per mile **and** hourly rate | Mileage pay plus hourly pay on non-driving time (C1, engineering decision) |
| Expenses for P&L | "pay minus expenses" | Variable costs only (decided C2) |
| Overtime | Overtime and double overtime | Daily thresholds, configurable per company, plus a per-driver exemption (decided C3). Not HOS [L]. |
| QuickBooks | "link" and "send data for payroll" | QuickBooks Online, Employees + TimeActivity + summary export (decided C4) |
| Trailer tracking | "live location of a trailer" | Inferred from the truck on the trailer's active trip (decided C5) |
| Folders | "standard set" is undefined | Per-entity standard folders (decided Q8) |
| Existing decisions vs the platform layer | PO-02, PO-07, PO-08 assume the super-admin acts inside tenants | PO-02 and PO-07 inside support sessions; PO-08 as an Asset Transfer (decided D11) |
| PO-22 email sync vs company contact email | Login email and company contact are merged today | Stop syncing; separate billing email (decided D3b) |

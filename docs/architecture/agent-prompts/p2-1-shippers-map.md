# P2-1: Shippers and the address picker (task 2.1)

```
Read docs/architecture/agent-prompts/README.md (rules, decisions and AUTO-FINISH), CLAUDE.md,
08-database-schema.md §1, §2 and §9 (shippers; the place columns of trip_stops), 02-requirement-matrix.md rows 4.4
and 6.1, 14-open-questions.md §2 "Maps (Phase 2 plan)" and §4 (OpenStreetMap policies), 12-performance-plan.md §3,
05-plans-features-entitlements.md (feature trips).
Skills: laravel-conventions, multi-tenant-safety, security-checklist, db-performance, seo-checklist, pest-testing.
Task: Phase 2 task 2.1 plus the shared map picker that P2-2 uses for trip stops. Package name: p2-1-shippers-map.

Build:
1. Migration for shippers exactly as 08 §9 (place_source + place_ref, no google_place_id), cFK company RESTRICT,
   UNIQUE(company_id, id), index (company_id, name), soft deletes. Model Shipper (BelongsToCompany, SoftDeletes),
   factory.
2. ShipperService and FormRequests: create, edit, activate/deactivate, soft delete. State code a US state or
   Canadian province (reuse App\Support\Fleet\PlateStates from P1-1), country US or CA, lat/lng in range, email and phone
   formats as elsewhere.
3. Shippers page under a new "Operations" sidebar group: yajra list (name, city/state, contact, active) with search,
   create/edit with the address picker, delete with data-confirm. Feature trips; permissions shippers.view and
   shippers.manage.
4. Geocoder: interface App\Services\Maps\Geocoder (search(query) and reverse(lat, lng) returning Place value
   objects: label, address parts, lat, lng, source, ref). One HTTP driver for the chosen service with a proper
   User-Agent and contact from config, a short timeout, results cached by normalised query, an app-wide rate limit
   that respects the service policy, and a per-user throttle on the route. A "none" driver turns search off and
   leaves manual entry. config/maps.php for the driver, base URL, tile URL and attribution (env keys documented in
   the final report). Authorized JSON route for staff who can manage shippers or trips.
5. Address picker Blade component (x-ui based): address fields, a Search button, a result list, and a Leaflet map
   (npm, bundled by Vite, loaded only on pages that use it; no CDN) with a draggable marker that sets lat/lng.
   Picking a result fills the fields, lat/lng, place_source and place_ref; moving the marker by hand sets
   place_source = manual. Without JS or without a geocoder the plain fields still work. OpenStreetMap attribution
   always visible. Rebuild and commit public/build.
6. Tests: CRUD and validation, deactivate keeps the row, soft delete, isolation for every new route
   (IsolationMatrixTest fixtures), read-only company, feature trips off hides the menu and refuses the routes,
   geocoder with Http::fake (cache hit, throttle, service error → empty result with a message, "none" driver),
   query budget for the list.

PENDING, recommended defaults:
- Geocoding service: recommend Photon (public komoot instance, partial-word matching) for search and reverse,
  with Nominatim as a second driver behind the same interface. Re-read both usage policies at plan time [A] and
  note them in 14 §4.
- Search scope: recommend US and Canada only (matches the state lists).
- Shipper names: recommend no uniqueness (two sites can share a name); the list shows city and state to tell them
  apart.
- Inactive shippers: recommend hidden from pickers but kept, and still shown on trips that use them.
- Stored geocoder data: recommend only place_source and place_ref, never the raw response (ODbL attribution
  stays on the map).

Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

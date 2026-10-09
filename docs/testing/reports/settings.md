# Module report: `settings` (site settings & Tawk.to)

Run date: 2026-10-09. Tests: `tests/Feature/Modules/Settings` (27 tests, all passing). The existing `tests/Feature/TawkToSettingTest.php` (15 tests) still passes.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| SET-01 | P0 | FIXED | `SiteSettingAuthorizationTest.php::tenants → it cannot update global site settings`; `…::tenants → it cannot open the site settings page`; `…::tenants → it cannot upload a logo`; `…::it forbids a user without settings permissions`; `…::it lets a user with only settings.view read but not write`; `SiteSettingUploadTest.php::logo → it rejects a PHP file`; `…::logo → it rejects an HTML file renamed to .png`; `…::logo → it rejects an SVG logo`; `…::logo → it rejects a plain string so the stored path cannot be pointed at another file`; `…::logo → it stores a real image under a hashed name with an extension derived from its content` (regression guard) | `routes/web.php`, `app/Http/Controllers/SiteSettingController.php` |
| SET-02 | P1 | FIXED (same route guard as SET-01) | `SiteSettingAuthorizationTest.php::tenants → it cannot update global site settings` (checks site name, meta title and GA id are unchanged); `…::it lets the super-admin view and update site settings` (regression guard) | `routes/web.php` |
| SET-03 | P2 | DISMISSED | `SiteSettingUploadTest.php::favicon → it rejects an SVG favicon`; `…::favicon → it rejects a PHP file named .ico` (both passed on the original code) | `SiteSettingController.php` (hardening only, see below) |
| SET-04 | P3 | FIXED | `AnalyticsAndTawkIdTest.php::validation → it rejects GA ids that are not GA4 measurement ids` (×5 payloads); `…::validation → it accepts a GA4 measurement id and an empty value`; `…::rendering → it JSON-encodes the GA id inside the gtag script on the welcome page`; `…::rendering → it cannot break the gtag string with a legacy stored value`; `…::rendering → it JSON-encodes the GA id in the admin layout`; `…::tawk.to ids → it rejects an embed url whose ids contain characters outside [A-Za-z0-9_-]` (regression guard, passed); `…::tawk.to ids → it JSON-encodes the stored ids when rendering the widget` (regression guard, passed) | `SiteSettingController.php`, `resources/views/welcome.blade.php`, `resources/views/layouts/main-layout.blade.php` |
| (Tawk) | n/a | regression guard | `SiteSettingAuthorizationTest.php::tenants → it cannot update tawk.to settings either` (passed; the route already had `permission:settings.edit`) | n/a |

### What changed

- **Authorization (SET-01, SET-02).** `GET /admin/settings/site` now requires `permission:settings.view` and `PUT /admin/settings/site` requires `permission:settings.edit`, the same permissions the Tawk.to routes already use. Only `super-admin` has `settings.*` in `PermissionSeeder`, so tenants get 403 on both routes. They never had a way to reach the page anyway: the sidebar's whole Settings group is already wrapped in `@can('settings.view')`.
- **Logo upload (SET-01).** The rule was `'logo' => 'nullable'`, with no file check at all. It is now `nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048`. Laravel's `mimes` checks the extension guessed from the file's **content**, and `store()` names the file `hashName()`, which uses that same guess. So a stored logo can only end in `.jpg`, `.png`, `.gif` or `.webp`, whatever the client calls it. The rule also blocks a second issue: the old rule accepted a plain string as `logo`, which was saved as the logo path and deleted on the next upload. That let a user point the logo at any file on the public disk, including other tenants' documents.
- **Favicon (SET-03).** The bug as described isn't real. The old rule was `image|mimes:…,svg,…`, but Laravel 12's `image` rule excludes SVG unless `image:allow_svg` is set, so SVG was already rejected. The same `image` rule also rejected real `.ico` files, even though the form offers `image/x-icon`. The rule is now `nullable|file|mimes:ico,png,jpg,jpeg,gif,webp|max:1024`, which removes the misleading `svg` and accepts `.ico`. This is covered by `SiteSettingUploadTest.php::favicon → it accepts a real .ico favicon (the form offers image/x-icon)`, which failed before the change.
- **GA id (SET-04).** Validation is now `regex:/^G-[A-Z0-9]+$/D`. The `D` modifier stops `$` from matching before a trailing newline. In both GA snippets, `gtag('config', '{{ $gaId }}')` is now `gtag('config', @json($gaId))`. The old code wasn't exploitable for XSS, because `e()` turns `'` into `&#039;`. But a stored trailing backslash escaped the closing quote and broke the script. `@json` makes any legacy value already in the database safe.
- **Tawk.to.** No change needed. The ID regex only captures `[a-zA-Z0-9_-]`, the partial renders the IDs with `@json`, and the encrypted round-trip is already covered by `TawkToSettingTest.php`.

### Test note

`UploadedFile::fake()->createWithContent()` sets the MIME type from the file **name**, so it can't test content spoofing. The tests for an HTML file named `.png`, a PHP file named `.ico`, and a real `.ico` use real `UploadedFile` instances (the `realUpload()` helper), so finfo sniffs the content, as it does in production.

## Out-of-scope changes

- `routes/web.php`: added `->middleware('permission:settings.view')` to `admin.settings.site.index` and `->middleware('permission:settings.edit')` to `admin.settings.site.update`. No other route changed.

## Deferred items (questions for the product owner)

None.

## Manual steps

No migrations and no permission changes.

1. **Check production for an uploaded shell (SET-01 was exploitable).** Before this fix, any subscribed tenant could upload any file as the logo. List `storage/app/public/settings/` and delete anything that isn't an image (`*.php`, `*.phtml`, `*.html`, `*.svg`, …). Also check `site_settings.logo` and `site_settings.favicon`, and check web-server access logs for requests to `/storage/settings/*.php`. If one is found, treat the server as compromised.
2. **Check the stored settings row.** Tenants could also change `site_name`, meta tags, contact info and `google_analytics_id`. Confirm the current values in `site_settings` are the ones you expect. A legacy GA id that doesn't match `G-XXXX` now renders safely, but saving the form will ask for a valid id.
3. The root `.htaccess` maps `.php` under `/storage` to the PHP handler. That belongs to the `access-control` module and was not changed here.

## Verification

- `php artisan test`: 10 failed, 343 passed. The 10 failures are the same tests that failed in the baseline (`ProfileTest` ×5, `Auth\RegistrationTest::new users can register`, `ComplianceReminderTest` ×4). Baseline was 10 failed, 316 passed. No regressions.
- `vendor/bin/phpstan analyse app/Http/Controllers/SiteSettingController.php app/Http/Controllers/TawkToSettingController.php app/Models/SiteSetting.php app/Helpers/settings.php --memory-limit=1G`: no errors.

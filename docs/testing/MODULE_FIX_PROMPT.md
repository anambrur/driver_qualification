# Module Fix Prompt

Paste everything below the line into Claude Code (or any coding agent) and change the `MODULE:` line.
Run one module per session, in the order listed in `docs/testing/MODULES.md`, and review + commit after each run.

---

```text
MODULE: lookups

You are fixing security and correctness bugs in a Laravel app (DriverFilesHub).
Work on the ONE module named above, then stop.

SOURCE OF TRUTH
Read docs/testing/MODULES.md, section for MODULE: its Scope files, Routes, and hotspot
table. Each hotspot ID (e.g. LKP-01) is one unit of work.

BASELINE (before changing anything)
- Run `php artisan test` and save the list of failing tests. You will compare against it at the end.
- Read every file in the module's Scope before editing.

PROCESS, per hotspot, highest severity first (P0 → P3)
1. Write a Pest test that reproduces it in tests/Feature/Modules/<Dir>/ (or tests/Unit/Modules/<Dir>/).
   Use Tests\Support\Actors, Tests\Support\StripeFake and the factories in database/factories.
   Add any missing factory the module section lists.
2. Run it. If it PASSES on current code, the hotspot is not real: mark DISMISSED, keep the test as a regression guard.
3. If it FAILS, make the smallest code change that makes it pass.
4. Run `php artisan test tests/Feature/Modules/<Dir> tests/Unit/Modules/<Dir>` and keep it green.

FIX CONVENTIONS (apply the same way in every module)
- Authorization: add `permission:<name>` middleware using permissions already defined in
  database/seeders/PermissionSeeder.php. Writes to global data (lookups, site settings,
  document types) are super-admin only; tenants get read-only access.
- Tenant isolation: scope every query by company (CompanyFilterTrait / company_id).
  Validate foreign ids with Rule::exists(...)->where('company_id', $companyId), not bare
  `exists:`. Cross-tenant access returns 404 (or 403 where the module already uses 403).
- XSS in DataTables/Blade: escape raw columns with e(). Replace inline onclick="fn(id,'name')"
  with data-* attributes plus a JS listener. Never rely on addslashes.
- Uploads: explicit mimes whitelist (no svg, html, php), store with $file->hashName(), and
  put sensitive documents (licenses, SSN-related, compliance docs) on the private disk,
  served through an authorized route.
- Never return exception messages to the client; log them and return a generic message.
- Schema: add new migrations only, never edit existing ones, never drop data without asking.
- Public endpoints: add `throttle:` limits where the hotspot asks for it.

HARD RULES
- Only touch the module's Scope, its tests and factories. If a fix needs a change outside
  scope (routes/web.php, a shared trait, middleware, config), keep it minimal and list it
  under "Out-of-scope changes" in the report.
- No unrelated refactors, renames or reformatting.
- Never run: migrate:fresh/db:seed against a non-test database, PermissionSeeder,
  composer update, git commit/push, or anything that calls real Stripe, Vonage or SMTP.
- If a hotspot needs a product decision (e.g. "should this be per-tenant or global?",
  "is this cascade delete intended?"), do NOT guess: mark it DEFERRED, write the
  question, and move on.

FINISH
1. Run the full `php artisan test`. Any test failing now that passed in the baseline is a
   regression: fix it or explain it.
2. Run `vendor/bin/phpstan analyse <scope paths> --memory-limit=1G`; fix new errors in touched files.
3. Write docs/testing/reports/<slug>.md with:
   - Table: ID | Sev | Result (FIXED / DISMISSED / DEFERRED) | Test file::test name | Files changed
   - Out-of-scope changes
   - Deferred items with the exact question for the product owner
   - Manual steps (new migrations to run, production config to change)
4. In docs/testing/MODULES.md, change each hotspot's Status for this module to FIXED / DISMISSED / DEFERRED.
5. Stop. Do not start the next module.
```

---

## Decisions the agent will defer to you

These hotspots need a product decision before they can be fixed. Expect them as `DEFERRED` in the reports:

| ID | Question |
|---|---|
| LKP-01 | Should lookup tables stay global (super-admin managed) or become per-tenant? |
| APP-07 | Should `drivers.email` be unique per company instead of globally? |
| CMP-05 | Should the policy PDF be per company instead of one global file? |
| AUTH-04 | Is deleting a profile supposed to cascade-delete the company's drivers and vehicles? |
| ACL-02 | When to schedule the dependency upgrade for the `composer audit` advisories? |
| ACL-06 | What do production `.env` and `.htaccess` actually contain? |

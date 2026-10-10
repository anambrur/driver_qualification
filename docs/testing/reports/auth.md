# Module report: `auth` (Authentication, registration & profile)

Run date: 2026-10-10. New tests are in `tests/Feature/Modules/Auth/AuthModuleTest.php` (10 tests). The existing `tests/Feature/Auth/RegistrationTest.php` and `tests/Feature/ProfileTest.php` were repaired. All auth tests pass.

**Full suite:** 1070 passed, 0 failed. The baseline before any change was 1054 passed, 6 failed (`Auth\RegistrationTest` ×1, `ProfileTest` ×5). Those 6 are now fixed. Nothing regressed.

**Larastan** on the scope (`routes/auth.php`, `app/Http/Controllers/Auth`, `LoginRequest.php`, `ProfileController.php`, `ProfileUpdateRequest.php`): 1 error, which was there before this run. It is in `VerifyEmailController.php:22`, a file this run did not change: `Verified` expects `MustVerifyEmail` but gets `App\Models\User|null`, because `User` does not implement `MustVerifyEmail`. Fixing it means changing the `User` model, which is outside this scope. The touched files have no errors.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| AUTH-01 | P2 | FIXED | `tests/Feature/Auth/RegistrationTest.php::new users can register`; `tests/Feature/ProfileTest.php` (all 5) | `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/ProfileTest.php` (tests only) |
| AUTH-02 | P2 | FIXED | `AuthModuleTest.php::AUTH-02 login enumeration → it returns the generic failure for an inactive account with a wrong password`, `→ it gives the same error for an unknown email and an inactive email with a wrong password`, `→ it tells an inactive user with the right password that the account is inactive and does not log them in`, `→ it still logs in an active user` | `app/Http/Requests/Auth/LoginRequest.php` |
| AUTH-03 | P2 | FIXED (password flashed to session) / DISMISSED (rollback + generic toast) | `AuthModuleTest.php::AUTH-03 registration failure handling → it does not flash the password into the session when registration fails`, `→ it rolls back the user and company when the company role is missing`, `→ it creates the user, company and company role on success` | `app/Http/Controllers/Auth/RegisteredUserController.php` |
| AUTH-04 | P2 | DEFERRED | `AuthModuleTest.php::AUTH-04 profile deletion cascade → it currently deletes the company and its drivers when the owner deletes their profile` (documents current behaviour) | none |
| AUTH-05 | P3 | FIXED | `AuthModuleTest.php::AUTH-05 company slug → it picks a different slug when the random suffix collides with an existing company`, `→ it builds a usable slug when the name has no latin characters` | `app/Http/Controllers/Auth/RegisteredUserController.php` |

No factories were missing. The tests use `UserFactory`, `CompanyFactory`, `DriverFactory` and `Tests\Support\Actors`.

### What changed

- **AUTH-01:** `RegistrationTest` now calls `Actors::seedAccessControl()` so the `company` role exists. `ProfileTest` uses `Actors::companyOwner()`, which has an active subscription, and the `/admin/profile` URLs. The test bodies are otherwise unchanged.
- **AUTH-02:** `LoginRequest::authenticate()` used to look the user up by email and say "Your account is inactive" before checking the password. Anyone could use that to find out which emails had inactive accounts. It now uses `Auth::attemptWhen()`, so the status is checked only after the password matches. A wrong password always gets the generic `auth.failed` message. An inactive user who enters the right password still sees the inactive message and is not logged in. The rate-limiter hit applies in both cases.
- **AUTH-03:**
  - On a failed registration, the controller called `back()->withInput()`. That flashed `password` and `password_confirmation` in plaintext into the session store (the `database` driver in production). It now flashes the input without those two fields.
  - The rest of the hotspot is DISMISSED. When the role is missing, rolling back the transaction is correct: the tests show no orphan user or company is left. The generic toast plus `Log::error` follows the "never return exception messages" convention.
- **AUTH-05:**
  - `companies.slug` has a unique index since CMP-04, so a random-suffix collision made registration fail with the generic error.
  - The slug is now built by `uniqueCompanySlug()`, which retries the suffix until no company has that slug.
  - The suffix is lowercased, so slugs stay lowercase in URLs.
  - When the name produces an empty slug (for example a name with no latin characters), the base falls back to `company`.
  - The unique index still catches a race between two concurrent signups.

## Out-of-scope changes

None. `tests/Feature/Auth/*` and `tests/Feature/ProfileTest.php` are the module's existing tests.

## Deferred items

- **AUTH-04:** The question for the product owner: "When a company owner deletes their account from `/admin/profile`, should that also permanently delete their company and every driver, vehicle, trailer, service log, maintenance schedule and asset group under it?" Today the FK cascades (`companies.user_id`, `drivers.company_id`, `vehicles.company_id` and others, all `ON DELETE CASCADE`) do exactly that, after only a password re-entry. The subscription and payment rows are deleted too.

  Other options:
  1. Block self-deletion while the user owns a company.
  2. Soft-delete or deactivate the account instead.
  3. Require contacting support.

  A related question: should the "Delete account" section be shown to sub-users (`user` role) and super-admins at all? Nothing was changed. The AUTH-04 test records the current behaviour, so any change has to be made on purpose.

## Manual steps

- No migrations.
- No config changes.

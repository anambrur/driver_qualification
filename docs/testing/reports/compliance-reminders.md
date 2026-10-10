# Module report: `compliance-reminders` (Reminders & digest)

Run date: 2026-10-10. New tests are in `tests/Feature/Modules/ComplianceReminders/ComplianceReminderEndpointsTest.php` (9 tests). The existing `tests/Feature/ComplianceReminderTest.php` (5 tests) was repaired. All 14 pass. Mail is always faked.

**Full suite:** 1054 passed, 6 failed. Before any change it was 1041 passed, 10 failed. The 4 `ComplianceReminderTest` failures are fixed. The 6 that still fail (`Auth\RegistrationTest` ×1, `ProfileTest` ×5) failed in the baseline too and belong to module `auth`. Nothing regressed.

**Larastan** on the 6 scope files: 17 errors, all already there before this run and none in lines this run changed. Most are `Relation 'company'/'documents' is not found in App\Models\Driver` (the relations on `app/Models/Driver.php` have no return types; that file is outside this scope) and `undefined property on Illuminate\Database\Eloquent\Model` in `ComplianceReminderService`, which takes `Model $asset` / `?Model $document`. Narrowing those types is a refactor and was left alone.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| REM-01 | P2 | FIXED | `ComplianceReminderTest.php` (all 5, repaired); `ComplianceReminderEndpointsTest.php::it queues a driver reminder for the tenant own driver`, `::it queues a vehicle reminder to the assigned driver`, `::it rejects a user without drivers.edit`, `::it does not let a tenant remind another company driver` | `tests/Feature/ComplianceReminderTest.php` (tests only) |
| REM-02 | P2 | FIXED | `ComplianceReminderEndpointsTest.php::it throttles repeated driver reminders from one user`, `::it throttles repeated vehicle reminders from one user` | `routes/web.php` |
| REM-03 | P3 | FIXED (error leak) / DISMISSED (digest de-dupe) | `ComplianceReminderEndpointsTest.php::it does not return the exception message from the driver endpoint`, `::it does not return the exception message from the vehicle endpoint`, `::it sends at most one digest per driver per day when the command runs twice` | `app/Http/Controllers/ComplianceReminderController.php` |

No factories were missing. The tests use `DriverFactory`, `DocumentTypeFactory`, `AssetGroupFactory` and `Tests\Support\Actors`.

### What changed

- **REM-01:** The old tests built a bare `super-admin` role with no permissions, so `permission:drivers.edit` / `permission:fleets.edit` refused them. The `createSuperAdminUser()` / `createCompanyForUser()` helpers now call `Actors::superAdmin()` / `Actors::companyOf()`. The test bodies are unchanged. New tests cover a subscribed tenant, a user without the permission (403), and a request for another tenant's driver (403/404, no mail sent).
- **REM-02:** Both send-reminder routes now also have `throttle:10,1,compliance-reminder`, i.e. 10 reminders per minute per user. The two routes share one bucket. Before the fix, 15 requests in a row all sent a mail.
- **REM-03:**
  - Both `catch (\Exception $e)` blocks returned `'Failed to send reminder: '.$e->getMessage()`, which could expose SQL or other internal details. They now `Log::error(...)` with the ids and the exception message, and return the generic message "Failed to send reminder. Please try again later." with status 500.
  - Digest de-dupe: running `compliance:send-digest-reminders` twice on the same day sends one digest per driver (tested on the `array` store). `Cache::add` is atomic on `database`, `redis` and `memcached`. `.env.example` and `config/cache.php` use `database` by default. Only the `file` store (not atomic) or the `array` store (not shared across processes) would weaken it, and the schedule entry already uses `withoutOverlapping()`. No code change was made; see the manual step below.

## Out-of-scope changes

- `routes/web.php`: added `throttle:10,1,compliance-reminder` to `admin.compliance.documents.send-reminder` and `admin.compliance.driver.documents.send-reminder`. Each middleware is now an array that also keeps the existing `permission:` entry.

## Deferred items

None.

## Manual steps

- No migrations.
- Production: keep `CACHE_STORE` set to `database` or `redis`, not `file` or `array`. This keeps the daily digest de-dupe and the `throttle:` limits reliable. If the scheduler runs on more than one server, consider adding `->onOneServer()` to the `compliance:send-digest-reminders` schedule in `routes/console.php`. That change was not made here.

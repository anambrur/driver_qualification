# Module report: `driver-hiring` (Hire / not-hire)

Run date: 2026-10-10. Tests are in `tests/Feature/Modules/DriverHiring/DriverHireStatusTest.php` (22 tests) and all pass. Mail is always faked.

**Full suite:** 1041 passed, 10 failed. Before any change it was 1019 passed, 10 failed. The same 10 tests fail in both runs: `Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4 and `ProfileTest` ×5 (these belong to modules `compliance-reminders` and `auth`). Nothing regressed.

**Larastan** on `DriverHireService.php`, `DriverHiredMail.php`, `DriverRejectedMail.php` and `DriverController.php`: the change adds no new errors. The errors that remain in these files were already there. They are all `Access to an undefined property App\Models\Driver::$company` (the `Driver::company()` relation has no return type) plus older ones in `DriverController`. They were not fixed because `app/Models/Driver.php` is outside this module's scope.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| HIRE-03 | P1 | DISMISSED | `DriverHireStatusTest.php::it returns 403 when another tenant hires or rejects my driver` (hire, reject); `::it returns 403 for a user without drivers.hire`; `::it lets the owning tenant hire and records the audit fields` | none |
| HIRE-01 | P2 | FIXED | `DriverHireStatusTest.php::it does not hire twice when two requests hold the same stale pending driver`; `::it does not flip a hired driver to rejected from a stale request`; guard: `::it rejects a double-submit over HTTP with 422 and one email` | `app/Services/Driver/DriverHireService.php` |
| HIRE-02 | P2 | DISMISSED | `DriverHireStatusTest.php::it accepts and stores every enum reason` (7 reasons); `::it returns 422 for a reason outside the enum`; `::it has a human label for every enum reason` (7 reasons) | none |

No factories were missing. The tests use the existing `DriverFactory` and `Tests\Support\Actors`.

### What changed

- **HIRE-01:**
  - `hire()` and `reject()` used to check `$driver->status !== 'pending'` on the model they were given, outside the transaction.
  - Two requests that loaded the driver while it was still pending both passed the check. The result was two hired emails, or a hired driver flipped to rejected.
  - Both methods now call `lockPendingDriver()` inside the transaction. It re-reads the row with `lockForUpdate()` and throws `InvalidArgumentException` unless the row is still pending.
  - The controller already maps that exception to a 422 with the fixed message "This driver is not in pending status." It is a message we wrote, not an internal error, so the controller was left unchanged.
  - Emails are still sent only after the transaction commits.
- **HIRE-02:**
  - The validator list (`not_good_fit, failed_drug_test, background_check_issues, cdl_issues, mvr_issues, psp_issues, other`) matches the enum in `2025_09_08_163240_create_drivers_table.php` exactly.
  - Every value is stored correctly and has a label in `getRejectionReasonLabel()`.
- **HIRE-03:**
  - The route has `permission:drivers.hire`, and the controller calls `authorizeCompanyAccess($driver)` before doing anything else. A different tenant gets 403 and nothing changes.

## Out-of-scope changes

None.

## Deferred items

None.

## Manual steps

None (no migrations or config changes).

# Module report: `otp-sms` (OTP, SMS & phone numbers)

Run date: 2026-10-10. Tests: `tests/Feature/Modules/OtpSms` (25 tests, 43 assertions). They pass, and so do the existing `tests/Feature/OTPServiceTest.php` and `tests/Feature/PhoneNumberServiceTest.php` (38 tests, 82 assertions in total). Vonage is always mocked.

**Full suite:** 866 passed, 10 failed. The baseline before any change was 841 passed, 10 failed. The same 10 tests fail in both runs: `Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4 and `ProfileTest` ×5. Nothing regressed.

**Larastan** on the scope files and `routes/console.php`: 1 error. Before this run there were 2.
- **Fixed:** `OTPService::getTimeSinceLastOTP()` returned a float, and it was a real bug (see OTP-02 below).
- **Still open:** `SmsService.php:61`, `method_exists(..., 'getEntity')` is always true. This error was already there, on a line I didn't touch.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| OTP-01 | P1 | FIXED | `OtpSmsPhoneValidationTest.php::rejects numbers outside the supported country codes` (UK, Latvia, Somalia, Sierra Leone); `::rejects NANP premium-rate area codes` (900/976, with and without country code); `::sendOTP never calls Vonage for an unsupported destination`; regression guard `::still accepts supported numbers` (US, BD, IN) | `app/Services/PhoneNumberService.php` |
| OTP-02 | P2 | FIXED (per-IP part DISMISSED: route throttles from APP-04 already exist) | `OtpSmsRateLimitTest.php::sendOTP refuses once the global hourly cap is reached`; regression guards `::public OTP-sending routes carry a per-IP throttle` (×3, passed before the fix), `::sendOTP still works under the global cap`; extra `::resendOTP reports the real remaining wait time` | `app/Services/OTPService.php` |
| OTP-03 | P2 | FIXED | `OtpSmsVerifyAttemptsTest.php::legacy OTP is invalidated after 3 wrong codes`; `::Vonage OTP record is invalidated after 3 failed checks and Vonage is not called again`; `::a new OTP resets the wrong-code counter`; regression guard `::legacy OTP still verifies after fewer than 3 wrong codes` | `app/Services/OTPService.php` |
| OTP-04 | P3 | FIXED | `OtpSmsHousekeepingTest.php::model:prune removes old OTP records`; `::OTP pruning is scheduled`; `::OTPService has no dead generateOtpCode helper`. The `otp_attempts:*` key is covered by the OTP-03 tests. | `app/Models/OtpVerification.php`, `app/Services/OTPService.php`, `routes/console.php` |
| OTP-05 | P3 | FIXED | `OtpSmsHousekeepingTest.php::SendSmsJob masks the phone number when logging a failure` | `app/Jobs/SendSmsJob.php`, `app/Services/SmsService.php` |

The test fixtures are in `tests/Feature/Modules/OtpSms/OtpSms.php`. The module section doesn't ask for any factories, so none were added.

### What changed

- **OTP-01:** `PhoneNumberService::isValid()` now accepts only the destinations it already validates in detail: +1 (NANP), +880 (BD) and +91 (IN).
  - Every other country code is refused. Before, any `+<country>` number was accepted.
  - NANP premium-rate area codes 900 and 976 are refused in national, `1…` and `+1…` formats.
  - The public application validates phones through this service, so international numbers outside those three countries are now rejected at the form too.
- **OTP-02:** `sendOTP()` refuses new sends once the number of OTP records created in the last hour, across all phones, reaches `config('services.vonage.otp_global_hourly_limit')`. The default is 200.
  - When the cap is hit, the user sees a generic message and a warning is logged.
  - **Per-IP limits:** these already exist as the `throttle:` middleware on the routes (APP-04). A test now guards them.
  - **Extra bug fix (found by Larastan):** `getTimeSinceLastOTP()` used `now()->diffInSeconds($past)`. Carbon 3 returns a negative float for that, so the "please wait N seconds" message was wrong. For example, it said 80 instead of 40.
- **OTP-03 / OTP-04:**
  - **Counting wrong codes:** each wrong code, on the legacy hash path or a failed Vonage `check()`, now increments `otp_attempts:{phone}`. That cache key was previously cleared but never written.
  - **Invalidation:** after 3 wrong codes, the record is marked used and Vonage is not called again.
  - **Reset:** the counter resets when a new OTP is sent.
  - **Housekeeping:**
    - `generateOtpCode()` was dead code and is removed.
    - `OtpVerification` now uses the `Prunable` trait.
    - `model:prune --model=OtpVerification` is scheduled daily.
- **OTP-05:** `SendSmsJob` now logs the masked number. `SmsService::maskPhoneNumber()` is now public so the job can reuse it.

## Out-of-scope changes

- `routes/console.php`: added `Schedule::command('model:prune', ['--model' => [OtpVerification::class]])->daily()->withoutOverlapping();`. Without a schedule, the `Prunable` trait never runs. The command is limited to `OtpVerification`, so no other model gets pruned by accident.

## Deferred items (questions for the product owner)

1. **OTP-01: allowed SMS countries.** "Should OTP SMS be limited to US/Canada only? Today +1, +880 (Bangladesh) and +91 (India) are allowed because the phone validator already had rules for them. The +1 range also includes Caribbean NANP countries (e.g. 876 Jamaica, 809 Dominican Republic), which are common toll-fraud destinations. Should those area codes be blocked too, and are the BD/IN numbers only for internal testing?"
2. **OTP-02: global cap value.** "Is 200 OTP sends per hour (across all companies) the right global ceiling? It is configurable via `services.vonage.otp_global_hourly_limit`."

## Observations (not fixed)

- `PhoneNumberService::normalize()` treats any 10-digit valid-NANP number as US, even when typed with a leading `+`. For example, `+53 5234 5678` becomes `+15352345678`. The SMS then goes to a US number rather than a premium destination, so this is not a toll-fraud risk. I left it alone because changing it would also change how `+2092776341`-style input is handled.
- `OTPService` keeps the wrong-code counter in the cache. Production needs a shared, persistent cache store (database/redis, not `array`) for the counter to work across requests.

## Manual steps

- No new migrations.
- Make sure the scheduler (`php artisan schedule:run` every minute) runs in production. Pruning depends on it.
- Optional: set `services.vonage.otp_global_hourly_limit` (the code default is 200). To make it settable from the environment, add `'otp_global_hourly_limit' => env('VONAGE_OTP_GLOBAL_HOURLY_LIMIT', 200)` to `config/services.php`. I didn't change that file.
- Confirm that `CACHE_STORE` in production is a persistent shared store.

## Decision follow-up: PO-11

Decided 2026-10-11: option A. +880/+91 are for internal testing only.

- `PhoneNumberService`: `BLOCKED_NANP_AREA_CODES` (US premium 900/976 plus the non-US/Canada NANP countries: Bahamas, Barbados, Anguilla, Antigua, BVI, Cayman, Bermuda, Grenada, Turks and Caicos, Jamaica 658/876, Montserrat, Sint Maarten, St Lucia, Dominica, St Vincent, Dominican Republic 809/829/849, Trinidad and Tobago, St Kitts). US territories (Puerto Rico, USVI, Guam, …) stay allowed as US numbers. +880/+91 validate only when `app()->environment('local', 'testing')`.
- Tests: `OtpSmsPhoneValidationTest.php` (US/Canada accepted; Caribbean rejected; BD/IN rejected in production and accepted on local/testing; `sendOTP` never calls Vonage for +1 876). `SubscriptionLifecycle` is green.
- Note: production must not run with `APP_ENV=local`, or +880/+91 are accepted.

## Decision follow-up: PO-12

Decided 2026-10-11: option A. 200 OTP sends/hour kept. DISMISSED, no code change.

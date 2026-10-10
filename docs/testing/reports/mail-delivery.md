# Module 21 `mail-delivery`: report (2026-10-10)

Baseline `php artisan test`: 1077 passed, 0 failed. After: 1081 passed, 0 failed (4 new tests). No regressions.

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| MAIL-01 | P2 | DISMISSED | `tests/Feature/Modules/MailDelivery/MailTemplateEscapingTest.php::MAIL-01: every HTML mail template escapes tenant and applicant controlled strings`; `::MAIL-01: CRLF in a document type name cannot inject headers through the subject` | none (regression guards only) |
| MAIL-02 | P3 | FIXED | `tests/Feature/Modules/MailDelivery/QueuedMailRetryTest.php::MAIL-02: queued mail jobs retry with backoff`; `::MAIL-02: a permanently failed queued mail is logged with its mailable and driver` | `app/Mail/Concerns/RetriesQueuedDelivery.php` (new), `app/Mail/{ApplicationSubmitted,DriverComplianceDigest,DriverComplianceReminder,DriverHired,DriverRejected,VehicleComplianceStatusReminder}Mail.php` |

## Notes

- **MAIL-01**: all 12 `emails/**` templates and the layout use `{{ }}`, and there is no `{!! !!}`. HTML payloads in driver first name, company name, document type name, asset label, rejection reason, and digest issue fields all render escaped. A CRLF + `Bcc:` in a document type name, which feeds the Subject of the reminder mailables, is Q-encoded into the folded Subject by Symfony Mime, so no extra header appears. The plain-text parts are not HTML-escaped in a way that matters: they go out as `text/plain`.
- **MAIL-02**: Queued mail had no `tries`/`backoff`/`failed()`. With `queue:work` at its default `--tries=1`, one transient SMTP error failed the job for good, and the lost mail was only visible in `failed_jobs`. The new trait sets `tries = 3`, `backoff = [60, 300]` and a `failed()` that calls `Log::error` with the mailable class, `driver_id`, `company_id` and the exception message. The recipient email is left out on purpose to keep PII out of the log.

## Out-of-scope changes

None.

## Deferred

None.

## Manual steps

- No migrations.
- If production workers run with an explicit `--tries=N`, the mailable's `$tries = 3` overrides it for these jobs. Confirm that the `failed_jobs` table exists and that someone watches the `error` log channel.

## Static analysis

`vendor/bin/phpstan analyse app/Mail app/Console/Commands/CheckMailDeliverability.php app/Providers/AppServiceProvider.php config/mail.php` reports 4 errors, all from before this change: `Access to an undefined property App\Models\Driver::$company` on the existing `$this->driver->company?->company_name` lines. The relation exists, but `Driver::company()` has no `BelongsTo` return type, so Larastan can't resolve it. Fixing it means editing `app/Models/Driver.php`, which is outside this module's scope, so it was left as is. The new trait adds no errors.

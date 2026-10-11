# Module report: `subscription-lifecycle` (Access gate, reminders & notifications)

Run date: 2026-10-10. Tests: `tests/Feature/Modules/SubscriptionLifecycle` (17 tests, 51 assertions), all passing. Vonage is always mocked (`SubscriptionLifecycle::fakeVonage()`). Mail uses the `array` mailer and the queue runs `sync`.

**Full suite:** 949 passed, 10 failed. The baseline before any change was 932 passed, 10 failed. The same 10 tests fail in both runs: `Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4 and `ProfileTest` ×5. Nothing regressed. The `Billing` module tests also pass after this run (41 tests). They exercise `SubscriptionNotificationService` through the webhooks.

**Larastan** on the scope paths: 18 errors, all on lines this run didn't change. My first pass added 2 errors (a `User` type on the new `notifyOrForget()` helper, because `$subscription->user` is typed as `Model`). I fixed both by leaving the parameter untyped, which matches `$user` in the command. The 18 that remain come from `Subscription::user()` / `plan()` having no relation generics, so `->name` and `->id` resolve on `Model`. That model is in the `billing` scope. `SendSubscriptionExpiryReminders.php` has 0 errors.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| SUB-01 | P2 | FIXED | `ExpiryReminderTest.php::SUB-01 → it retries the reminder on the next run when sending throws`; `::SUB-01 → it keeps processing other subscriptions when one send throws`; `::SUB-01 → it removes the activation log rows when notify() throws so a webhook retry resends it` | `app/Console/Commands/SendSubscriptionExpiryReminders.php`, `app/Services/Billing/SubscriptionNotificationService.php` |
| SUB-02 | P2 | FIXED | `ExpiryReminderTest.php::SUB-02 → it sends the 7-day reminder when the scheduler missed day 7`; `::SUB-02 → it sends the 3-day reminder when day 3 was missed, without resending the 7-day one`; `::SUB-02 → it sends the expired notice and closes the trial when the expiry-day run was missed`; guards (passed before) `::SUB-02 → it sends each window once, not every day inside it`, `::SUB-02 → it does not send anything for a subscription that ended long ago`, `::SUB-02 → it does not remind a healthy paid subscription` | `app/Console/Commands/SendSubscriptionExpiryReminders.php` |
| SUB-03 | P2 | FIXED | `ExpiryReminderTest.php::SUB-03 → it sends the SMS to the normalized number`; `::SUB-03 → it does not send to an invalid or unsupported number` (free text / premium rate / unsupported country); `::SUB-03 → it retries the SMS on the next run after Vonage fails`; `::SUB-03 → it does not mark the SMS as sent when Vonage is not configured` | `app/Notifications/SubscriptionExpirySmsNotification.php`, `app/Console/Commands/SendSubscriptionExpiryReminders.php` |
| SUB-04 | P2 | DEFERRED | `DashboardGateTest.php::currently lets an expired tenant open /dashboard (DEFERRED SUB-04)` (pins current behaviour); guard `::shows an unsubscribed tenant only their own company counts` | none |

Shared fixture: `tests/Feature/Modules/SubscriptionLifecycle/SubscriptionLifecycle.php` binds a mocked `Vonage\Client`. The module section doesn't list any missing factories, so none were added.

### What changed

- **SUB-01:**
  - Both the command and `SubscriptionNotificationService` wrote the `subscription_notification_logs` rows and then called `notify()`. If `notify()` threw, the rows stayed, so the reminder (or the activation / payment-failed notice) was never sent. In the command, the exception also aborted the whole run, so every later subscription was skipped.
  - **Command:** `notify()` is now wrapped. On failure, the mail and database log rows for that window are deleted, the error is logged with the subscription id, and the run continues with the next subscription. The next run retries.
  - **Service:** on failure, the log rows are deleted and the exception is **rethrown**, so callers behave as before. For the webhook, that means a 500 and a Stripe redelivery, which now resends the notice.
  - Limit: with a real (non-sync) queue, `notify()` only dispatches the job, so this covers dispatch failures. A failure inside the queued mail job is retried by the queue worker, not by this log.
- **SUB-02:**
  - Reminders only fired on an exact `$days === $window` match, so one missed scheduler day lost that reminder for good.
  - `windowFor($days)` now picks the current window: the smallest of 7/3/1 that is still ≥ the days left. For example, 6–4 days left → the 7-day window, and 2 days left → the 3-day window.
  - Log rows are keyed by the window, so each window is sent once, and a missed window is sent late with the real days remaining.
  - The expired notice (window 0) is also sent if the expiry day was missed, for up to `EXPIRED_CATCH_UP_DAYS = 2` days after it. Subscriptions that ended longer ago are ignored. Trials are still limited by `shouldReceiveExpiryReminders()` (24h `justExpired`).
  - The `SubscriptionExpiringSoon` / `SubscriptionExpired` events now fire once, when the mail/database notice is actually sent, not on every run. No listeners are registered today.
  - Rollout note: subscriptions that are already partway into a window (e.g. 5 days left with no 7-day log row) will get that window's reminder on the first run after deploy. Subscriptions that already have a log row for their current window are not reminded again.
- **SUB-03:**
  - `SubscriptionExpirySmsNotification::send()` now validates `company.phone` with the existing `PhoneNumberService::isValid()`, the same rule the OTP/SMS module uses (NANP, Bangladesh and India only, no premium-rate numbers). The number is normalized to E.164 and sent without the `+`, as `OTPService` does for Vonage.
  - Invalid numbers are skipped, and the log line doesn't contain the number.
  - If the SMS isn't sent for any reason (invalid number, Vonage not configured, Vonage error), the `sms` log row is deleted, so the next run inside the window retries.
  - Exception text from Vonage is only logged, never shown to a user.

## Out-of-scope changes

None.

## Deferred items

- **SUB-04:** `/dashboard` (`auth` + `verified`) has no `Subscribed` middleware, but `/admin/dashboard` (the same `DashboardController::index`) does. Tests show it only exposes the tenant's own aggregate counts, never another tenant's data or the super-admin company stats, so there's no cross-tenant leak.
  - Adding the gate would change onboarding: login, registration and email verification all redirect to `route('dashboard')`. New and lapsed accounts would land on `/pricing` instead of the dashboard.
  - **Question for the product owner:** Should an account without an active subscription (new sign-up with no plan yet, or an expired one) still be able to see `/dashboard` with its own counts? Or should `/dashboard` require `Subscribed` like `/admin/dashboard` and send them to the pricing page?
  - If gating is wanted: add `'Subscribed'` to the `/dashboard` route in `routes/web.php`, and flip the first test in `DashboardGateTest.php` to expect a redirect to `pricing.plans`.

## Manual steps

- No new migrations, no config changes.
- Optional: make sure `services.vonage.sms_from`, `api_key` and `api_secret` are set in production. Without them, expiry SMS are skipped, and are now retried daily inside each window instead of being marked sent.

## Decision follow-up: PO-16

Decided 2026-10-11: option B. `/dashboard` has `auth`, `verified` and `Subscribed`. New and lapsed accounts land on `/pricing/plans`. Super-admins pass `Subscribed`.

- `routes/web.php`.
- Tests: `DashboardGateTest.php`. The pinning test became `sends an expired tenant from /dashboard to pricing (decided PO-16, 2026-10-11)`; added a no-plan redirect test, a subscribed own-counts test and a super-admin test. Registration → verify → pricing → trial → dashboard is covered in `Billing/TrialAbuseTest.php`. `Auth`, `Dashboard` and `Billing` are green.

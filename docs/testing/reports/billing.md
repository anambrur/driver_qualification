# Module report: `billing` (Plans, checkout, portal, webhooks)

Run date: 2026-10-10. Tests: `tests/Feature/Modules/Billing` (41 tests, 99 assertions), all passing. No test ever reaches Stripe: every Stripe call goes through `StripeFake::bindClient()` and webhooks are signed locally with `StripeFake::signature()`.

**Full suite:** 932 passed, 10 failed. The baseline before any change was 891 passed, 10 failed. The same 10 tests fail in both runs: `Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4 and `ProfileTest` ×5. Nothing regressed.

**Larastan** on the scope files plus `app/Models/User.php`: 13 errors, down from 17 before this run. My change to `User::activeSubscription()` added one error (`HasMany::accessible()` unknown). I fixed it by adding a `@return HasMany<Subscription, $this>` generic to `User::subscriptions()`, and that also cleared 4 older errors in `BillingController`. The 13 that remain were already there, on lines this run didn't touch: `Subscription::accessEndsAt()` typed as string, `StripeObject::$object`, `Model::$id`, and redundant `?->` in `WebhookProcessor`.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| BIL-01 | P2 | DISMISSED | `WebhookIdempotencyTest.php::creates one subscription and one activation notice when checkout.session.completed and customer.subscription.created both arrive`; `::creates one subscription when the events arrive in the opposite order`; `::ignores a replay of the same event id`; `::does not double-sync when the success page and the webhook both process the session`; `::lets the database reject a second row for the same Stripe subscription (concurrent insert guard)`; `::rejects an unsigned webhook` | none |
| BIL-02 | P2 | FIXED | `AccessibleParityTest.php::agrees with isAccessible()` (19-state dataset; 6 failed before the fix); regression guard `::keeps the scope grouped when chained after another where` | `app/Models/Subscription.php` |
| BIL-03 | P2 | FIXED (trial record erased) / DEFERRED (per-account trial rule) | `TrialAbuseTest.php::keeps the trial record when a later paid Stripe subscription is synced`; `::refuses a trial after a paid subscription has replaced an earlier trial`; guards `::requires login to start a trial`, `::starts a trial once and refuses a second one for the same user`; documents current behaviour `::currently gives a brand-new account its own trial (per-user rule, see DEFERRED BIL-03)` | `app/Services/Stripe/WebhookProcessor.php` |
| BIL-04 | P2 | FIXED | `CheckoutSuccessOwnershipTest.php::does not sync a session whose metadata names another user, even if client_reference_id matches`; guards `::does not sync another user's checkout session` (passed before), `::syncs the logged-in user's own session`, `::requires login` | `app/Http/Controllers/CheckoutController.php` |
| BIL-05 | P3 | FIXED | `ActiveSubscriptionPerfTest.php::hydrates only accessible subscriptions when resolving the active one`; `::builds a new Stripe client when the secret changes`; guards `::still returns null when nothing is accessible`, `::keeps the Subscribed gate working` | `app/Models/User.php` (out of scope), `app/Services/Stripe/StripeClientFactory.php` |
| (convention) | P3 | FIXED | `BillingErrorLeakTest.php::does not show the Stripe error message when resuming fails`; guard `::resumes a subscription on its grace period` | `app/Http/Controllers/BillingController.php` |

The shared Stripe object fixtures are in `tests/Feature/Modules/Billing/Billing.php`. The module section doesn't list any missing factories, so none were added.

### What changed

- **BIL-01 (dismissed):** The hotspot assumed there was no unique constraint, but `subscriptions.stripe_subscription_id` already has one (migration `2026_07_31_110000`). `payments.stripe_invoice_id` and `stripe_checkout_session_id` are unique too.
  - Upserts look the row up by Stripe id first.
  - "Activated" and "payment failed" notices are deduplicated through the unique `subscription_notification_logs` row.
  - Under true concurrency, the losing insert fails on the unique index. The webhook then returns 500 and Stripe retries, and the retry takes the update path. On `/checkout-success`, the error is caught and logged.
  - So event replays and overlapping events produce one subscription, one payment and one notice. An event-id store isn't needed for correctness.
- **BIL-02:**
  - I rewrote `scopeAccessible()` as an SQL mirror of `isAccessible()`. It uses `COALESCE(ends_at, trial_ends_at, current_period_end)`, the same fallback as `accessEndsAt()`.
  - Before the fix, these were wrong:
    - `past_due` was never in the scope.
    - `incomplete` + `cancel_at_period_end` was in the scope.
    - An expired trial row with `cancel_at_period_end = false` was in the scope.
    - Rows whose `ends_at` had passed still matched on a future `current_period_end`.
  - The scope wasn't used anywhere before this run. It is now used by `User::activeSubscription()` (BIL-05).
- **BIL-03:**
  - `WebhookProcessor::handleSubscriptionUpsert()` "claimed" the user's latest `active/trialing/past_due/incomplete` row with no Stripe id when it synced a new Stripe subscription. That included an expired free-trial row, whose status stays `trialing`.
  - The paid subscription overwrote that row, setting `source` to `stripe` and `billing_cycle` to `monthly`. `hasUsedTrial()` then returned false, so the user could start another free trial once the paid plan lapsed. The paid activation email was also skipped.
  - Trial rows are now excluded from the claim (`source != 'trial'` and `billing_cycle != 'trial'`). Admin-granted rows are still claimed as before.
  - The cross-account part is DEFERRED (see below).
- **BIL-04:**
  - `success()` accepted the session when either `metadata.user_id` or `client_reference_id` matched the current user. `syncCheckoutSession()` then assigned the subscription to `metadata.user_id` first.
  - Now every owner id the session carries must equal the current user.
  - Sessions created by this app always set both ids to the same user, so this wasn't exploitable through normal checkout. It's defence in depth.
- **BIL-05:**
  - `User::activeSubscription()` now applies `->accessible()` in SQL before the PHP `isAccessible()` check. For a user with 25 expired rows and 1 active row, it hydrates 1 model instead of 26, still in 1 query.
  - `StripeClientFactory` still caches the client statically, but only while `services.stripe.secret` is unchanged. A rotated key or a config change in a long-running worker or test now builds a new client.
- **Convention fix:** `BillingController::resume()` showed `$e->getMessage()` from Stripe in a toast. It now shows a generic message, and the exception is still logged.

## Out-of-scope changes

- `app/Models/User.php`:
  - `activeSubscription()` gains `->accessible()` (BIL-05).
  - `subscriptions()` gains a `@return HasMany<Subscription, $this>` PHPDoc for Larastan.
  - No behaviour change beyond the query filter, which the BIL-02 parity tests prove equivalent.
- No changes to `routes/web.php`, middleware, shared traits, config or migrations.

## Deferred items (questions for the product owner)

1. **BIL-03: who is a free trial limited to?** "Today a free trial is limited to one per *user account*. Anyone can register a new account with a new email and get another trial. Also, `User` doesn't implement `MustVerifyEmail`, so the email address is never verified, and adding the `verified` middleware to `/checkout/{plan}` would have no effect. Should trials be limited (a) per verified email, which means turning on email verification app-wide and is an auth-module change, (b) per company (by name, phone or DOT/MC number), (c) per payment card, which means collecting a card for trials via Stripe Checkout `trial_period_days`, or (d) is per-account acceptable?"

## Observations (not fixed)

- **Shared Stripe account.** `CheckoutService` tags customers with `application`, `app_url` and `app_host` metadata "for shared Stripe accounts". If the Stripe account really is shared with another app, every webhook endpoint receives that app's events too. `WebhookProcessor` resolves the user only by `metadata.user_id` or the customer id, and never checks `metadata.app_host`. So another app's `customer.subscription.created` with `user_id = N` would create a subscription for user N here. Worth confirming whether the account is shared. If it is, ignore events whose `metadata.app_host` isn't this app's.
- Expired free trials keep `stripe_status = 'trialing'` forever; nothing moves them to `canceled`. Access is still computed correctly from the dates.
- `CheckoutController::checkout()` shows `InvalidArgumentException` messages in toasts. They're all app-authored strings (from `TrialActivationService` and `CheckoutService`), so I left it.

## Manual steps

- None. No migrations, no config changes.

## Decision follow-up: PO-15

Decided 2026-10-11: option A. One free trial per verified email.

- `User implements MustVerifyEmail`. Registration sends the verification email through Laravel's default `Registered` listener. `/checkout/{name}` and `/checkout-success` have `verified`. After clicking the link, the user returns to the checkout they started (`redirect()->intended`). Changing the email on Profile already clears `email_verified_at`, so a new email must be verified before the next checkout.
- Accounts created by a super-admin are auto-verified (unchanged). Existing unverified accounts must verify before their next checkout. The owner says there are no real accounts yet.
- Tests: `TrialAbuseTest.php`. The pinning test became `gives each verified email one trial (decided PO-15, 2026-10-11)`; added "refuses a trial to an account whose email is not verified" and the registration → verify → trial flow.
- Out-of-scope: `app/Models/User.php`, `routes/web.php`.
- Manual step: production needs working mail delivery for verification emails (see PO-20).

## Decision follow-up: PO-18

Decided 2026-10-11: the Stripe account is shared.

- `WebhookProcessor::handle()` ignores (logs at info, returns 200) subscription, checkout and invoice events whose `app_host` differs from `CheckoutService::appHost()`. `app_host` is read from `metadata`, `subscription_details.metadata` or `parent.subscription_details.metadata`. Events with no `app_host` are processed as before.
- Tests: `WebhookSharedAccountTest.php`.

## Decision follow-up: PO-22 (billing item)

- New command `subscriptions:close-expired-trials` (`--dry-run` only counts). It marks local trials (`stripe_subscription_id` null, `stripe_status` trialing) whose end date has passed as `canceled`. Scheduled daily at 10:00, after the 09:00 expiry reminders, which still send the "expired" notice.
- Tests: `CloseExpiredTrialsTest.php`.

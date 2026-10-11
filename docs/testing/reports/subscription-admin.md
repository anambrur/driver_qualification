# Module report: `subscription-admin` (Super-admin billing tools)

Run date: 2026-10-10. Tests are in `tests/Feature/Modules/SubscriptionAdmin` and all pass. Stripe is always mocked. In `LocalSubscriptionActionsTest`, `StripeClientFactory` is bound with `shouldNotReceive('make')`, so any call to Stripe fails the test. `SubscriptionNotificationService` is mocked too.

**Full suite:** 1019 passed, 10 failed. Before any change it was 949 passed, 10 failed. The same 10 tests fail in both runs: `Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4 and `ProfileTest` ×5. Nothing regressed. The `Billing` and `SubscriptionLifecycle` module suites still pass.

**Larastan** on `SubscriptionAdminController.php` and `app/Services/Stripe/SubscriptionService.php`: no errors.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| SADM-01 | P1 | DISMISSED | `RouteAuthorizationTest.php::it returns 403 for non super-admins` (15 routes × tenant / plain user; also checks that nothing changed); `::it redirects guests to login`; `::it lets a super-admin open the read-only pages` | none |
| SADM-02 | P2 | FIXED | `PlanFeaturesScriptEscapeTest.php::it escapes a stored feature on the edit page`; `::it escapes old() features on the create page after a validation error`; `::it escapes old() features on the edit page after a validation error`; `::it still hands the exact feature text to Alpine` | `resources/views/admin/plans/create.blade.php`, `resources/views/admin/plans/edit.blade.php` |
| SADM-03 | P2 | FIXED | `LocalSubscriptionActionsTest.php::it reactivating a suspended admin grant keeps its original end date`; `::it a tenant cannot turn an admin grant into lifetime access via billing cancel + resume`; `::it does not echo exception messages when reactivate fails`. Guards that passed before the fix: `::it grants a time-bounded complimentary subscription`, `::it refuses to grant while the user has an accessible subscription` (active / trial / past_due / canceled-in-grace), `::it allows a grant once the previous subscription has expired`, `::it expires an admin grant locally without calling Stripe`, `::it expires a trial locally without calling Stripe`, `::it suspends an admin grant until its original end date`, `::it reactivating a suspended trial keeps the trial end date`, `::it refuses to reactivate an expired local subscription` | `app/Http/Controllers/Admin/SubscriptionAdminController.php`, `app/Services/Stripe/SubscriptionService.php` (out of scope, see below) |

No factories were missing. The tests use the existing `PlanFactory` and `SubscriptionFactory`.

### What changed

- **SADM-01:** `role:super-admin` (Spatie `RoleMiddleware`) is on the whole group, so tenants and plain users get 403 on every route and guests are redirected to login. The tests stay as a regression guard.
- **SADM-02:**
  - `json_encode()` already escapes `/`, so `</script>` could not break out.
  - However, `<!--<script>` passed through unescaped. Inside a `<script>` block that puts the HTML parser into the "script data double escaped" state, which can swallow the real closing `</script>`.
  - Both views now use `@js(...)` (`Js::from`, with `JSON_HEX_TAG|APOS|AMP|QUOT`), which emits `JSON.parse('…')` with `<`, `>`, `&` and quotes hex-escaped.
  - `create.blade.php` now uses `@js(old('features', ['']))`, which keeps the old `[""]` default.
- **SADM-03:**
  - Stripe is never called for subscriptions without a Stripe id: `expire`, `suspend` and `reactivate` already take the local path in `SubscriptionService`.
  - `grant` already refuses while `hasActiveSubscription()` is true.
  - Two real bugs were found:
    1. **Lifetime access after reactivate:** `SubscriptionService::resume()` set `ends_at = null` and `cancel_at_period_end = false` on local subscriptions. For an admin grant (non-trial), `isAccessible()` returns true forever for an `active` row with `cancel_at_period_end = false`, and nothing ever renews or ends it.
       - Admin path: suspend + reactivate turned a 30-day grant into unlimited access.
       - Tenant path: `POST /billing/cancel` + `POST /billing/resume` did the same.
       - Fix: local **non-trial** subscriptions now keep `cancel_at_period_end = true` and `ends_at = accessEndsAt()` on resume. That matches the state `grant` creates.
       - Trials keep their previous behaviour, which an existing `Billing` test pins (`BillingErrorLeakTest::it resumes a subscription on its grace period`). They are still limited by `trial_ends_at`.
    2. **Exception text shown to the admin:** `reactivate()` flashed `$e->getMessage()`, which can include Stripe API errors and request ids. It now logs the error and shows a generic message, the same way `expire`/`suspend` already did.

## Out-of-scope changes

- `app/Services/Stripe/SubscriptionService.php` (`billing` scope), `resume()`: the local-subscription branch was changed as described in SADM-03. The change is limited to rows with no `stripe_subscription_id` and `billing_cycle != 'trial'`. The Stripe path and the trial path are unchanged.

## Deferred items

None.

## Manual steps

- No migrations and no config changes.
- Optional data check in production: look for admin grants that were already turned into lifetime access by an earlier reactivate or resume:
  `SELECT id, user_id FROM subscriptions WHERE stripe_subscription_id IS NULL AND billing_cycle <> 'trial' AND stripe_status = 'active' AND cancel_at_period_end = 0 AND ends_at IS NULL;`
  Whether those rows should get an end date again is a business decision. This run did not change any data.

## Decision follow-up: PO-19

Decided 2026-10-11: option B (fix by hand from the subscription-admin page). The owner notes the system is still in development with no real accounts, so there is nothing to fix today. No code change. Run the read-only query from "Manual steps" again before go-live.

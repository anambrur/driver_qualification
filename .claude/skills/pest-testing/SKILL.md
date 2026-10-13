---
name: pest-testing
description: Pest test conventions for this repo. Use when writing or fixing tests.
---
- Pest syntax (`it()`/`test()`), `RefreshDatabase`, factories (create missing factories rather than hand-built rows). Helpers in `tests/Support`.
- Cover per endpoint: success, validation failure, unauthorized role, cross-tenant access, and edge cases.
- Fake externals: `Http::fake`, `Queue/Mail/Notification/Event::fake`, Stripe and SMS via fakes of `app/Services/Stripe/*` and `SmsService`.
- Set Spatie team context in test setup.
- Assert behavior and DB state, not implementation. One reason to fail per test.
- Run only the relevant file/filter while iterating: `php artisan test --filter=...`.

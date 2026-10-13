---
name: queue-and-jobs
description: Queue, job, and scheduled-task rules. Use for PDFs, SMS, email, reminders, webhooks, or any slow/async work.
---
- Queue anything slow or external (PDF, SMS, mail, Stripe sync, reminders); controllers only dispatch.
- Jobs: idempotent, pass IDs (not models) incl. `company_id`, set `$tries`, `backoff()`, `$timeout`; `ShouldBeUnique` for dedup; implement `failed()` to log.
- Notifications/Mail: `ShouldQueue`; send after commit (`afterCommit`).
- Scheduled commands: `withoutOverlapping()`, chunk the work, tenant context per record.
- Test with `Queue::fake()` plus a direct `handle()` call.

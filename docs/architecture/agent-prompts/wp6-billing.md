# WP6: Company billing (task 0.8)

```
Read CLAUDE.md, 07-subscription-billing.md, 05-plans-features-entitlements.md (§7),
11-migration-strategy.md (§5 step 5 and the Billing tests row), and skills
security-checklist, queue-and-jobs, repository-service-pattern.
Task: Phase 0 task 0.8.

Move billing to the company: Stripe customer per company, Prices, checkout, webhook inbox
(stripe_events) + processing job (idempotent by event id, ordering guard, ignore foreign
app_host), access state (full/grace/read_only/blocked; 7-day grace), trials (one per
verified email, top tier), notifications to Owner + billing_email, admin actions,
the subscribed middleware.
Update Billing/* and SubscriptionLifecycle/* tests per the 11 §5 table.

Plan mode first; wait for approval. Finish with the quality-gate skill.
Acceptance: two users of one company share one subscription; a second trial in the same
company or with the same owner email is refused.
```

---
name: security-checklist
description: Security checks for Laravel code. Use when adding routes, forms, uploads, auth/OTP, Stripe/webhooks, or handling driver PII and documents.
---
- AuthZ on every route (middleware + Policy). Check ownership to prevent IDOR.
- Validate all input via FormRequest; whitelist with `$request->validated()`, never `$request->all()`.
- Uploads: validate `mimes` + `max`, store on private disk, random names, serve through authorized controller; never trust client filename/mime.
- Output: Blade `{{ }}` by default; `{!! !!}` only for sanitized content. No raw SQL with interpolation (use bindings).
- Auth/OTP: rate limit (`throttle`), expire and single-use OTP, hash secrets, no user enumeration in messages.
- Stripe: verify webhook signature, idempotent handling by event id, never trust client amount/plan.
- PII (SSN, license, medical docs): hide from `$hidden`/logs/responses, encrypt sensitive columns (`encrypted` cast), log access to documents.
- Secrets only via `.env`/config; never log tokens or full request bodies.
- State-changing routes use POST/PUT/DELETE with CSRF.

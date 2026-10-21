# P1-3: Google and Facebook login and registration, web (task 1.1)

```
Read docs/architecture/agent-prompts/README.md (rules and AUTO-FINISH), CLAUDE.md,
08-database-schema.md §4 (users, social_accounts), 10-workflows.md §1 (registration),
04-platform-administration.md (EmailIdentityService, D3a), 05-plans-features-entitlements.md §2 (platform
capabilities), 14-open-questions.md §4 [L], 02-requirement-matrix.md row 1.4.
Skills: security-checklist, multi-tenant-safety, laravel-conventions, seo-checklist, pest-testing.
Task: Phase 1 task 1.1. Package name: p1-3-social-login.

Build:
1. composer require laravel/socialite (check Laravel 13 support first). Google and Facebook in
   config/services.php from env; keys in .env.example.
2. social_accounts migration and model exactly as 08 §4. Not tenant-scoped (login path), like users.
3. Routes /auth/{provider}/redirect and /auth/{provider}/callback (google|facebook only, throttled,
   noindex), plus connect / disconnect from the profile.
4. A service in app/Services/Auth handles the callback:
   a. Known (provider, provider_user_id): log in through the same checks as password login (user status,
      company status, last_login_at, session regenerate, audit "login" with the provider).
   b. Unknown id, email of an existing tenant user: no auto-link (PENDING); tell them to log in with the
      password and connect the provider from the profile.
   c. Email of a platform user: refuse (D3a) with the same generic message.
   d. New email: keep the provider profile in the session (never the token), show a short
      "finish sign-up" form with the fields RegisteredUserController requires (company name, ...), then the same
      EmailIdentityService::claim + CompanyProvisioner path; email_verified_at only when the provider says
      the email is verified; random password; social_accounts row; the one-trial-per-email rule unchanged.
   e. Provider returns no email (possible on Facebook): refuse with a clear message.
5. Profile: "Connected accounts" card (connect, disconnect; warn when the user never set a password and
   link to password reset).
6. Google and Facebook buttons on login and register, only for providers that are configured and enabled.
7. Tests with a faked Socialite driver: branches a–e, invalid state, inactive user, inactive company,
   platform email, provider already linked to another user, disconnect, buttons hidden when off,
   SEO (callback noindex; login and register stay public), ACL_PUBLIC_ROUTES reasons.

PENDING, recommended defaults:
- Auto-link by matching email? Recommend no (avoids account takeover through unverified Facebook emails).
- Enable switch: env only, or env secrets + a per-provider on/off in Platform > Settings (site_settings)?
  Recommend the toggle, default off.
- Accept a company invitation with social login? Recommend not in this package; invitations stay password-based.
- Driver-role users: same rule as password login.
- [L] For the product owner: Google consent-screen verification, Facebook app review, and Facebook's
  data-deletion instructions URL (recommend a section on the privacy page; no callback endpoint). List them in the report.

Plan mode first; wait for approval. Then run AUTO-FINISH from the README.
```

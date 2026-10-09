# Module report: `public-application` (public 10-step driver application, no auth)

Run date: 2026-10-09. Tests: `tests/Feature/Modules/PublicApplication`: 125 tests, all passing, none left in `known-issue`.

The first pass deferred APP-06, APP-07 and the `check-status` part of APP-05. The product owner answered all three the same day, and they are implemented; see "Product decisions" below. Every hotspot is now FIXED.

## Results

| ID | Sev | Result | Test file::test name | Files changed |
|---|---|---|---|---|
| APP-01 | P0 | FIXED | `ApplicationSessionTest.php::APP-01: the step pages require the applicant session → it does not let an anonymous visitor overwrite an applicant's FMCSA consent`; `…::it rejects an anonymous POST to every store step` (steps 2–10); `…::it does not render another applicant's step page to an anonymous visitor` (steps 2–10); `…::it does not render step 1 without a session`; `…::it does not let an applicant write to another applicant's record at the same company` (steps 2–10); `…::it does not show another applicant's step page to an applicant`; `…::it does not let an anonymous visitor withdraw an application`; regression guards `…::APP-01: the applicant can still complete their own application → it renders every step page…` / `…renders step 1…` / `…saves every step…` / `…saves step 1…` | `app/Http/Controllers/ApplicationFormController.php` |
| APP-02 | P0 | FIXED | `ApplicationCompanyBindingTest.php::APP-02: … → it does not accept a session verified for another company` (steps 2, 5, 7, 10); `…::it rejects a session verified for another company even when driver_id passes validation`; `…::it does not write to a driver of another company even if the session claims the slug` (steps 2, 5, 7, 10); `…::it does not render a driver of another company under this company's URL`; `…::it does not render step 1 for a driver of another company`; `…::it validates driver_id against the {slug} company, not any driver` | `ApplicationFormController.php`, `app/Http/Requests/PublicApplication/ValidatesApplicationDriver.php` (new), the 9 `Store*Request.php` files with a `driver_id` |
| APP-03 | P1 | FIXED | `ResumeApplicationTest.php::APP-03: resuming an application requires the SMS code → it does not grant an application session for phone and date of birth alone`; `…::it sends the old /resume page to the OTP-verified resume flow`; regression guards `…::it still resumes an application after the SMS code is verified`, `…::it does not resume when the SMS code is wrong` | `ApplicationFormController.php` |
| APP-04 | P1 | FIXED | `OtpAbuseTest.php::APP-04: resend-otp only texts the phone verified in this session → it refuses to text a phone number given in the request when no OTP was started`; `…::it ignores a phone number in the request and texts the session phone`; `…::APP-04: public OTP and lookup endpoints are rate limited per IP → it returns 429 once the limit is used up` (8 endpoints); `…::it shares one SMS budget across send-otp, resend-otp, check-resume and check-status`; `…::it counts each IP separately` | `routes/web.php`, `ApplicationFormController.php` |
| APP-05 | P1 | FIXED | check-resume: `ResumeApplicationTest.php::APP-05: check-resume does not reveal which phones have applied → it answers the same for a phone with and without an application`; `…::it only texts phones that have an application`; `…::it answers the same when the SMS for a known phone is refused (e.g. resend cooldown)`. check-status: `StatusCheckTest.php::APP-05: checking application status requires an SMS code → it does not show the status for phone and date of birth alone`; `…::it texts a code only when the phone and date of birth match an application`; `…::it answers the same whether or not anything matched`; `…::it shows the status after the right code`; `…::it does not show the status after a wrong code`; `…::it does not show anything when phone and date of birth did not match, even with a valid code for that phone`; `…::it sends the code page back to the form when no check was started`; `…::it does not carry a check started at another company` | `ApplicationFormController.php`, `routes/web.php`, `resources/views/application/status-verify.blade.php` (new) |
| APP-06 | P2 | FIXED | `WithdrawApplicationTest.php::APP-06: withdrawing an application → it withdraws the applicant's own application and ends their session`; `…::it withdraws a submitted (pending) application resumed by SMS code`; `…::it shows a withdraw button on the step pages` (steps 1, 2, 5, 10); `…::it does not let an applicant resume a withdrawn application`; `…::APP-06: a withdrawn applicant can apply again → it lets a withdrawn applicant request a new SMS code`; `…::it reopens the withdrawn application as a draft after the SMS code is verified`; `…::it prefers an open draft over a withdrawn application for the same phone` | `database/migrations/2026_10_09_000200_add_withdrawn_status_to_drivers_table.php` (new), `ApplicationFormController.php`, `resources/views/application/partials/withdraw-application.blade.php` (new), `resources/views/application/steps/step{1..10}-*.blade.php` |
| APP-07 | P2 | FIXED | `DuplicateEmailTest.php::APP-07: a driver email is unique per company → it lets a driver who applied to company A apply to company B with the same email`; `…::it explains, instead of failing silently, when the email is taken at the same company`; `…::it lets the applicant save step 1 again with their own email`; `…::it enforces it in the database: same email in two companies, never twice in one` | `database/migrations/2026_10_09_000300_make_driver_email_unique_per_company.php` (new), `app/Http/Requests/PublicApplication/StoreApplicationStep1Request.php` |
| APP-08 | P2 | FIXED | `InactiveCompanyTest.php::APP-08: an inactive company accepts no applications → it does not show the landing or start page`; `…::it does not send an SMS code`; `…::it does not create a draft driver on OTP verification`; `…::it does not save application steps, even with a session started while it was active` (steps 2, 7, 10); `…::it does not save step 1`; `…::it does not render application steps`; `…::it does not send a resume code`; `…::it does not resend an SMS code` | `ApplicationFormController.php` |
| APP-09 | P2 | FIXED | `ApplicationSessionHygieneTest.php::APP-09: application session hygiene → it no longer exposes the empty save-progress endpoint`; `…::it rotates the session id when the phone is verified`; `…::it rotates the session id when an application is resumed by SMS code`; control `…::it keeps the session id across requests that grant nothing` | `ApplicationFormController.php`, `routes/web.php` |
| APP-10 (new) | P1 | FIXED | `UploadNamingTest.php::APP-10: public uploads are stored under a server-chosen name → it stores licence images with the extension of their content` (`.html`, `.svg`, `.htm`, `.js`); `…::it stores the medical card and forfeiture document with the extension of their content`; `…::it stores the step 1 photo with the extension of its content`; `…::it gives two uploads in the same second different names` | `app/Services/Driver/DriverDocumentWizardService.php`, `app/Services/Driver/DriverCrudService.php` |

No hotspot was dismissed. Every hotspot test failed on the original code before its fix.

The `check-status` guard test from the first pass, which checked that wrong-DOB and unknown-phone gave the same redirect, was replaced by `StatusCheckTest.php`.

### What changed

- **APP-01 and APP-02: session guard.**
  - `checkApplicationSession()`, whose redirect every caller ignored, is replaced by `applicationDriver(Company $company, $driverId = null): Driver`. It returns the applicant's own driver only when all of these hold:
    - the session has `application_started`, `application_driver_id` and `verified_phone`;
    - `verified_company_slug` and `verified_company_id` match the `{slug}` company;
    - `driver_id` (route or form), if given, equals the session's driver;
    - the driver belongs to that company, has `source = public_application`, and has status `draft` or `pending`.
  - Otherwise it throws an `HttpResponseException` that redirects to the start page with the existing "Please start the application process first." toast. The module already redirected here; it just never took effect.
  - All step GET/POST actions and `withdraw` now take the driver from this helper instead of `Driver::findOrFail($request->driver_id)`.
  - The form requests validate `driver_id` with `Rule::exists('drivers', 'id')->where('company_id', <{slug} company>)`, through the new `ValidatesApplicationDriver` trait.
  - `StoreApplicationLicenseRequest` used to decide whether the licence images are required from the *submitted* `driver_id`, which let anyone probe another applicant's documents. It now uses the session's driver.
- **APP-03: resume without OTP.**
  - `GET /resume` and `POST /resume` no longer grant a session. They redirect to the landing page, whose "Return to Application" button already resumes through `check-resume` → SMS code → `check-resume-otp`.
  - No page linked to `/resume`. `resources/views/application/resume.blade.php` is now unused but was not deleted.
- **APP-04: OTP abuse.**
  - `resendOtp` only texts `otp_verification_phone` from the session. A `phone` field in the request is ignored, and with no session phone it returns 400. The verify page JS already sent only the session phone, so the UI is unaffected.
  - Per-IP limits (inline `throttle:`, each group with its own key prefix):

    | Group | Routes | Limit |
    |---|---|---|
    | `application-sms` | `send-otp`, `resend-otp`, `check-resume`, `check-status` | 5 per 10 min, shared budget |
    | `application-verify` | `verify-otp`, `check-resume-otp`, `POST status/verify` | 10 per min |
    | `application-lookup` | `POST resume` (retired form, now only redirects) | 10 per 10 min |

    `OTPService` still applies its own per-phone cooldown and attempt limits on top.
- **APP-05: phone enumeration.** `check-resume` returns `{success: true, requires_otp: true, phone}` whether or not the phone has an application, and also when sending the SMS fails. Only real applicants are texted. Send failures are logged, not returned. The trade-off: someone without an application sees "code sent" and receives nothing.
- **APP-08: inactive companies.** A new `activeCompany($slug)` resolver (`status = active`, 404 otherwise) replaces every bare `Company::where('slug', …)->firstOrFail()` in the controller. The two JSON resume endpoints filter on `status = active` too, and `resendOtp` checks it first.
  - An applicant already in progress at a company that becomes inactive now gets a 404 on the next step. Their draft is kept.
- **APP-09: dead state.**
  - Removed `application_session_token`. It was written but never checked, and never left the server.
  - Removed the `save-progress` route and its empty `saveProgress()` method, and the unused `getOtpFromRequest()`.
  - What the token seemed meant for, binding the application to the browser that proved the phone, is now done with `Session::regenerate()` when `verifyOtp` or `verifyResumeOtpPhone` grants the application session. This prevents session fixation.
- **APP-10 (found during this run): upload names.**
  - Licence, medical card and forfeiture files (`DriverDocumentWizardService::replaceFile`) and the driver photo (`DriverCrudService::storePhoto`) were saved on the public disk under the **client's** file extension.
  - A real PNG named `x.html` or `x.svg` passes `image|mimes:png`, which checks the content, not the name. It was then served from `/storage/...` as HTML. That is stored XSS on the app's origin, from an unauthenticated form.
  - Files are now stored as `<prefix>_<$file->hashName()>`: a random name whose extension comes from the content.
  - This also fixes a collision: the photo name was `driver_photo_<time()>`, so two uploads in the same second overwrote each other, and replacing one later deleted the other.
  - Both services are shared with the admin driver wizard, which gets the same fix. Already-stored files are not renamed.

## Out-of-scope changes

- `routes/web.php`, public application group only:
  - added `->middleware('throttle:…')` to `send-otp`, `verify-otp` (POST), `resend-otp`, `resume` (POST), `check-resume`, `check-resume-otp` and `check-status`;
  - added `GET` and `POST` `status/verify` (`public.application.status.verify`, `public.application.status.verify.submit`);
  - removed the `save-progress` route.

  No other route changed.
- `resources/views/components/progress-bar.blade.php`: wrapped the inline `function getEditStepUrl()` declaration in `if (! function_exists(...))`. Without this, a second render in the same PHP process is a fatal "Cannot redeclare", which made every step-page test after the first one crash. It is also a real bug under Octane or queued rendering. The same partial is used by the admin driver wizard, and its behaviour there is unchanged.
- `app/Services/Driver/DriverCrudService.php` is in scope only "as used here", but `storePhoto()` is shared with the admin driver create/edit (APP-10).
- Two new migrations on the shared `drivers` table (APP-06, APP-07). Both are new files; no existing migration was edited.

## Product decisions (answered 2026-10-09) and how they were implemented

| Question | Decision | Result |
|---|---|---|
| APP-06: what does "withdraw" do? | New `withdrawn` status (migration), and a withdrawn applicant may apply again | DONE |
| APP-07: is a driver email unique per company or platform-wide? | Per company | DONE |
| APP-05: should the status check require an SMS code? | Yes | DONE |

- **APP-06: withdraw.**
  - The new migration adds `withdrawn` to the `drivers.status` enum and a nullable `withdrawn_at` timestamp.
  - `withdraw` sets both, then clears the whole application session (also `verified_phone`, `verified_company_*` and `phone_verified_at`), and redirects to the start page.
  - Only the applicant's own session can call it (APP-01/02), for a `draft` application or a submitted `pending` one resumed by SMS code.
  - Every step page (1–10) now has a "Withdraw application" button below the progress sidebar, from the new partial `application/partials/withdraw-application.blade.php`. It asks for confirmation (SweetAlert, or `confirm()` as a fallback) through a `data-confirm-withdraw` attribute and one listener. There are no inline handlers.
  - A withdrawn application can't be resumed; resume only matches `draft` or `pending`.
  - **Applying again** uses the normal start: phone, then SMS code. `sendOtp` already blocks only `active` and `pending` phones.
    - On a verified code, `verifyOtp` first looks for an open draft for that phone at the company.
    - Otherwise it reopens the most recent withdrawn application for that phone: status back to `draft`, `withdrawn_at` cleared.
    - The applicant then goes through the steps again, with their earlier answers and documents prefilled.
    - **Why reopen instead of a new record:** email is now unique per company. A second record for the same person, with the same email at the same company, would hit that constraint at step 1.
    - The trade-off: the earlier `withdrawn_at` isn't kept once they re-apply.
  - Admin side: no change was needed. The driver list, the driver page and `DriverHireService::getStatusLabel()` already fall back to `ucfirst($status)`, so withdrawn applicants show as "Withdrawn" in neutral grey. The status-count tabs have no "Withdrawn" tab, though; see Notes.
- **APP-07: email unique per company.**
  - The new migration replaces the global `unique(email)` with `unique(company_id, email)`.
  - Step 1 validates the email with `Rule::unique('drivers', 'email')->where('company_id', <{slug} company>)->ignore(<session driver>)`. A same-company duplicate now gets the message "This email is already used by another application at this company." instead of a swallowed insert error.
  - The admin driver forms (drivers module) have no `unique` email rule. They rely on the database, which now allows the same email in different companies. A same-company duplicate still fails on insert there; see Notes.
- **APP-05: status check with an SMS code.**
  1. `POST check-status` (phone + date of birth) always redirects to a new page, `GET status/verify`, with "If an application matches those details, we have sent a code to that phone."
     - It texts a code only when the phone and date of birth match an application at that company.
     - Send failures are logged, not shown.
     - It keeps the phone, date of birth and company in the session.
  2. `POST status/verify` checks the code with `OTPService::verifyOTP`. Only then does it look up the application and render the existing `status-result` page; the label map now includes "Withdrawn".
     - Wrong code: "Invalid or expired code." and back to the code page.
     - No check started, or a check started for another company: back to the status form.
     - Valid code but no matching application, which can only happen with a code sent for some other reason: "No application found", shown only after the phone has been proven.
  - `check-status` now sends SMS, so it moved into the shared `application-sms` budget. `status/verify` uses the `application-verify` limit.

## Manual steps

- **Run the two new migrations:** `php artisan migrate`.
  - `2026_10_09_000200_add_withdrawn_status_to_drivers_table` alters the `drivers.status` enum, which rewrites the table on MySQL. Run it in a quiet window.
  - `2026_10_09_000300_make_driver_email_unique_per_company` swaps `drivers_email_unique` for `drivers_company_id_email_unique`. Existing data already satisfies the new index, because it was globally unique.
  - Both migrations were run in the SQLite test database only, not against MySQL.
- Rollback notes:
  - The withdrawn migration's `down()` turns any `withdrawn` drivers into `inactive` and drops `withdrawn_at`.
  - The email migration's `down()` fails if two companies now share a driver email. Fix those rows first.
- No config changes.
- Production: rate limits use the default cache store. With several app servers, make sure `CACHE_STORE` is shared (redis or database), or each server counts separately. Behind a load balancer or proxy, `TrustProxies` must be configured, or every visitor shares the proxy's IP and one budget.
- Existing uploads keep their old names. If any were stored with a non-image extension, e.g. `.html` or `.svg`, find them with `find storage/app/public/images/documents storage/app/public/images/drivers -type f ! -iname '*.jpg' ! -iname '*.jpeg' ! -iname '*.png' ! -iname '*.webp' ! -iname '*.gif'` and review or remove them.
- Applicants mid-application when this deploys keep their session. Their session ID rotates the next time they verify an SMS code.

## Notes / observations (not fixed)

- **Sensitive documents are on the public disk.** Licence front/back, medical card and forfeiture images are stored under `storage/app/public/images/documents` and are readable by anyone who has the URL. The conventions say they belong on the private disk, served through an authorized route. The admin driver views read them through public URLs, so moving them belongs to the `drivers` / `driver-compliance` modules. File names are now unguessable (APP-10), which limits but doesn't remove the exposure.
- **Step pages with `?edit=1` crash.** The public step pages accept `?edit=1`, which renders admin "Skip to next step" links using an undefined `$driver_id` and returns a 500. Only the applicant's own session can reach them now.
- **Steps 6–9 crash without a `DriverDocument` row.** They read properties on a null `$driverDocument`. Step 2 always creates the row first, so it can't happen in the normal flow.
- **Admin drivers list (drivers module):**
  - The status-count tabs (`DriverController::index`) have no "Withdrawn" tab. Withdrawn applicants appear under "All".
  - Admins can't set `withdrawn` themselves; `updateStatus` validates `in:draft,submitted,…`.
  - Admin create/edit with an email that another driver in the *same* company already uses fails on the database constraint and shows the generic error. A per-company `Rule::unique` in `StoreDriverRequest` would give a clear message.
  - The dashboard counts (`DashboardController`) don't include `withdrawn` in any bucket.
- **Step 1 email check reveals use within the company.** It says when an email is already used by another application at the same company. Only a visitor with a verified phone reaches step 1, so this is a small disclosure, kept because the clear message was asked for.
- **Step 1 can change `main_phone`.** It is validated as free text, so after OTP verification an applicant can replace the verified phone with any number. That number is later used for resume and status lookups.
- **Larastan** on the scope paths reports 16 errors. All of them predate this change:
  - Most come from `App\Models\Driver`, whose relations (`company`, `licenses`, `driver_documents`) have no return types, so Larastan can't see them. That model is outside this scope.
  - 7 × `booleanNot.alwaysFalse` in the untouched `calculateCurrentStep()`.
  - 1 × `nullsafe.neverNull` in `DriverCrudService::resolvePlaceName()`.

  No new errors in the touched code.

## Test run comparison

- Baseline `php artisan test`: 10 failed, 191 passed.
- After the first pass: 10 failed, 296 passed.
- After the product decisions: 10 failed, 316 passed. The same 10 tests fail as in the baseline (`Auth\RegistrationTest` ×1, `ComplianceReminderTest` ×4, `ProfileTest` ×5), so there are no regressions.
- `tests/Feature/Modules/PublicApplication`: 125 passed. No `known-issue` tests remain.
- Larastan on the scope paths plus the two new migrations shows the same 16 pre-existing errors (see above). Nothing new.

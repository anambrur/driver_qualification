<?php

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| UI-13 (phase 2, batch 4c): the application layout without the jQuery, SweetAlert2, DataTables 1.13
| CDNs and the preloader; showAppAlert/showAppConfirm on the shared x-ui dialog; the entry pages
| (landing, start, OTP, status, complete) on x-application.entry. All of them stay noindex.
*/

beforeEach(function () {
    Applicant::fakeOtp();
    $this->company = Applicant::company(['company_name' => 'A & B Trucking', 'phone' => '(209) 555-0142', 'email' => 'hr@ab.test']);
});

function errorBag(array $messages): ViewErrorBag
{
    return (new ViewErrorBag)->put('default', new MessageBag($messages));
}

/** @return array<string, Closure(): string> */
function applicationEntryUrls(): array
{
    return [
        'landing' => fn () => route('application.form', test()->company->slug),
        'start' => fn () => route('public.application.start', test()->company->slug),
        'verify otp' => function () {
            test()->withSession(['otp_verification_phone' => '+12095550142']);

            return route('public.application.verify.otp', test()->company->slug);
        },
        'status' => fn () => route('public.application.status', test()->company->slug),
        'status verify' => function () {
            test()->withSession(['status_check_company_id' => test()->company->id]);

            return route('public.application.status.verify', test()->company->slug);
        },
        'complete' => fn () => route('public.application.complete', test()->company->slug),
    ];
}

dataset('entry pages', fn () => applicationEntryUrls());

it('renders every entry page on the shared shell without the legacy CDNs', function (Closure $url) {
    $html = $this->get($url())->assertOk()
        ->assertSee('aria-label="Change colour theme"', false)
        ->assertSee('id="confirm-dialog"', false)
        ->assertDontSee(['sweetalert2', 'code.jquery.com', 'cdn.datatables.net', 'kit.fontawesome.com', 'class="fas ', 'Swal.'], false)
        ->assertDontSee('x-init="window.addEventListener(\'DOMContentLoaded\', () => {setTimeout(() => loaded = false', false)
        ->getContent();

    preg_match_all('~<style[^>]*>(.*?)</style>~s', $html, $styles);
    expect($styles[1])->toHaveCount(1)
        ->and($styles[1][0])->toStartWith(':root{--brand:')
        ->and(substr_count($html, '<h1'))->toBe(1)
        // Entry pages follow the theme; only the unmigrated steps pin light mode.
        ->and($html)->toContain('(document.documentElement, "system", null)');
})->with('entry pages');

it('keeps every entry page noindex', function (Closure $url) {
    $this->get($url())->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
})->with('entry pages');

it('escapes the company name once and dials the company phone', function () {
    $this->get(route('application.form', $this->company->slug))->assertOk()
        ->assertSee('A &amp; B Trucking', false)
        ->assertDontSee('&amp;amp;', false)
        ->assertSee('href="tel:2095550142"', false)
        ->assertSee('href="mailto:hr@ab.test"', false)
        ->assertDontSee(['2092776341', '123456789']);

    $this->get(route('public.application.complete', $this->company->slug))->assertOk()
        ->assertSee('application for A &amp; B Trucking.', false)
        ->assertDontSee('&amp;amp;', false);
});

it('hides the help phone when the company has none', function () {
    $this->company->forceFill(['phone' => null])->save();

    $this->get(route('application.form', $this->company->slug))->assertOk()
        ->assertDontSee('href="tel:', false)
        ->assertDontSee('123456789');
});

it('keeps the resume flow endpoints on the landing page', function () {
    $this->get(route('application.form', $this->company->slug))->assertOk()
        ->assertSee('id="resume-dialog"', false)
        ->assertSee('/application/check-resume`', false)
        ->assertSee('/application/check-resume-otp`', false)
        ->assertSee(json_encode(route('public.application.start', ['slug' => ':slug'])), false);
});

it('keeps the start form, its old input and server errors', function () {
    $this->from(route('public.application.start', $this->company->slug))->followingRedirects()
        ->post(route('public.application.send.otp', $this->company->slug), ['phone' => '209555', 'confirm_phone' => '209556'])
        ->assertOk()
        ->assertSee('action="'.route('public.application.send.otp', $this->company->slug).'"', false)
        ->assertSee(['name="phone"', 'name="confirm_phone"', 'name="_token"'], false)
        ->assertSee(['value="209555"', 'value="209556"'], false)
        ->assertSee('id="phone-error"', false)
        ->assertSee('role="alert"', false);
});

it('keeps the otp form, the resend endpoint and the otp error', function () {
    $this->withSession(['otp_verification_phone' => '+12095550142'])
        ->get(route('public.application.verify.otp', $this->company->slug))
        ->assertOk()
        ->assertSee('action="'.route('public.application.submit.otp', $this->company->slug).'"', false)
        ->assertSee('name="otp" id="otp-input"', false)
        ->assertSee(route('public.application.resend.otp', $this->company->slug), false)
        ->assertSee('phone: "+12095550142"', false);

    $this->withSession(['otp_verification_phone' => '+12095550142', 'errors' => errorBag(['otp' => 'The code is wrong.'])])
        ->get(route('public.application.verify.otp', $this->company->slug))
        ->assertSee('The code is wrong.');
});

it('keeps the status form fields and errors', function () {
    $this->withSession(['errors' => errorBag(['phone' => 'Enter a phone.'])])
        ->get(route('public.application.status', $this->company->slug))
        ->assertOk()
        ->assertSee('action="'.route('public.application.check.status', $this->company->slug).'"', false)
        ->assertSee(['name="phone"', 'name="date_of_birth"'], false)
        ->assertSee('Enter a phone.');
});

it('keeps the steps light-only and on Font Awesome until batch 4d, without SweetAlert or jQuery', function () {
    $driver = Applicant::draft($this->company);

    $html = $this->withSession(Applicant::session($driver))
        ->get(route('public.application.step8', ['slug' => $this->company->slug, 'driver_id' => $driver->id]))
        ->assertOk()
        ->assertSee('id="confirm-dialog"', false)
        ->assertSee('kit.fontawesome.com', false)
        ->assertDontSee(['sweetalert2', 'code.jquery.com', 'cdn.datatables.net', 'window.showAppAlert = function'], false)
        ->getContent();

    expect($html)->toContain('(document.documentElement, "system", "light")');
});

it('asks before withdrawing through the shared confirm dialog', function () {
    $driver = Applicant::draft($this->company);

    $this->withSession(Applicant::session($driver))
        ->get(route('public.application.step2', ['slug' => $this->company->slug, 'driver_id' => $driver->id]))
        ->assertOk()
        ->assertSee('action="'.route('public.application.withdraw', ['slug' => $this->company->slug, 'driver_id' => $driver->id]).'"', false)
        ->assertSee('data-confirm="Withdraw application?"', false)
        ->assertSee('data-confirm-cancel="Keep my application"', false);
});

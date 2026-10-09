<?php

use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| APP-03: POST /resume handed out a full application session (and so access to SSN, licence,
| documents) for phone + date of birth alone, no OTP. The landing page already has the
| OTP-verified resume flow (check-resume → check-resume-otp); /resume must not bypass it.
|
| APP-05: check-resume answered "No application found with this phone number" for unknown
| phones, so anyone could test which phone numbers have applied to a company.
*/

beforeEach(function () {
    $this->otp = Applicant::fakeOtp();
    $this->company = Applicant::company();
    $this->applicant = Applicant::draft($this->company, [
        'main_phone' => '+12025550101',
        'date_of_birth' => '1990-01-01',
        'first_name' => 'Jane',
    ]);
});

describe('APP-03: resuming an application requires the SMS code', function () {
    it('does not grant an application session for phone and date of birth alone', function () {
        $this->post(route('public.application.verify.resume', $this->company->slug), [
            'phone' => '2025550101',
            'date_of_birth' => '1990-01-01',
        ])->assertRedirect(route('application.form', $this->company->slug));

        expect(session('application_driver_id'))->toBeNull();

        $this->get(route('public.application.step1', $this->company->slug))
            ->assertRedirect(route('public.application.start', $this->company->slug));
    });

    it('sends the old /resume page to the OTP-verified resume flow', function () {
        $this->get(route('public.application.resume', $this->company->slug))
            ->assertRedirect(route('application.form', $this->company->slug));
    });

    it('still resumes an application after the SMS code is verified', function () {
        $this->postJson(route('public.application.check.resume', $this->company->slug), ['phone' => '(202) 555-0101'])
            ->assertOk()
            ->assertJson(['success' => true, 'requires_otp' => true]);

        $this->postJson(route('public.application.verify.resume.otp', $this->company->slug), [
            'phone' => '+12025550101',
            'otp' => '123456',
        ])->assertOk()->assertJson([
            'success' => true,
            'redirect' => route('public.application.step1', ['slug' => $this->company->slug, 'driver_id' => $this->applicant->id]),
        ]);

        expect(session('application_driver_id'))->toBe($this->applicant->id);

        $this->get(route('public.application.step1', $this->company->slug))->assertOk();
    });

    it('does not resume when the SMS code is wrong', function () {
        $this->otp->allows('verifyOTP')->andReturn(['success' => false, 'message' => 'Invalid OTP']);

        $this->postJson(route('public.application.verify.resume.otp', $this->company->slug), [
            'phone' => '+12025550101',
            'otp' => '000000',
        ])->assertJson(['success' => false]);

        expect(session('application_driver_id'))->toBeNull();
    });
});

describe('APP-05: check-resume does not reveal which phones have applied', function () {
    it('answers the same for a phone with and without an application', function () {
        $known = $this->postJson(route('public.application.check.resume', $this->company->slug), ['phone' => '(202) 555-0101'])
            ->assertOk()->json();
        $unknown = $this->postJson(route('public.application.check.resume', $this->company->slug), ['phone' => '(202) 555-0199'])
            ->assertOk()->json();

        expect(array_keys($unknown))->toBe(array_keys($known))
            ->and($unknown['success'])->toBe($known['success'])
            ->and($unknown['requires_otp'])->toBe($known['requires_otp']);
    });

    it('only texts phones that have an application', function () {
        $this->otp->expects('sendOTP')->once()->with('+12025550101')
            ->andReturn(['success' => true, 'method' => 'direct_sms']);

        $this->postJson(route('public.application.check.resume', $this->company->slug), ['phone' => '(202) 555-0101']);
        $this->postJson(route('public.application.check.resume', $this->company->slug), ['phone' => '(202) 555-0199']);
    });

    it('answers the same when the SMS for a known phone is refused (e.g. resend cooldown)', function () {
        $this->otp->allows('sendOTP')->andReturn(['success' => false, 'message' => 'Please wait before requesting a new OTP']);

        $known = $this->postJson(route('public.application.check.resume', $this->company->slug), ['phone' => '(202) 555-0101'])->json();
        $unknown = $this->postJson(route('public.application.check.resume', $this->company->slug), ['phone' => '(202) 555-0199'])->json();

        expect($known)->toBe(array_merge($unknown, ['phone' => $known['phone']]));
    });

});

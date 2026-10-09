<?php

use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| APP-05 (check-status): the status page showed an applicant's name, phone and status to anyone
| who knew their phone number and date of birth.
| Product decision (2026-10-09): checking status requires an SMS code. The first step answers
| the same whether or not an application matches; only a real applicant is texted.
*/

beforeEach(function () {
    $this->otp = Applicant::fakeOtp();
    $this->company = Applicant::company();
    $this->applicant = Applicant::draft($this->company, [
        'main_phone' => '+12025550101',
        'date_of_birth' => '1990-01-01',
        'first_name' => 'Jane',
        'last_name' => 'Roe',
        'status' => 'pending',
    ]);
});

function checkStatus(string $phone, string $dob = '1990-01-01')
{
    return test()->post(route('public.application.check.status', test()->company->slug), [
        'phone' => $phone,
        'date_of_birth' => $dob,
    ]);
}

describe('APP-05: checking application status requires an SMS code', function () {
    it('does not show the status for phone and date of birth alone', function () {
        checkStatus('2025550101')
            ->assertRedirect(route('public.application.status.verify', $this->company->slug))
            ->assertDontSee('Roe');

        $this->get(route('public.application.status.verify', $this->company->slug))
            ->assertOk()
            ->assertDontSee('Jane')
            ->assertDontSee('Under Review');
    });

    it('texts a code only when the phone and date of birth match an application', function () {
        $this->otp->expects('sendOTP')->once()->with('+12025550101')
            ->andReturn(['success' => true, 'method' => 'direct_sms']);

        checkStatus('2025550101');
        checkStatus('2025550101', '1991-01-01');
        checkStatus('2025550199');
    });

    it('answers the same whether or not anything matched', function () {
        $match = checkStatus('2025550101');
        $wrongDob = checkStatus('2025550101', '1991-01-01');
        $unknown = checkStatus('2025550199');

        foreach ([$match, $wrongDob, $unknown] as $response) {
            $response->assertRedirect(route('public.application.status.verify', $this->company->slug));
        }
    });

    it('shows the status after the right code', function () {
        checkStatus('2025550101');

        $this->post(route('public.application.status.verify.submit', $this->company->slug), ['otp' => '123456'])
            ->assertOk()
            ->assertSee('Jane')
            ->assertSee('Under Review');

        expect(session('status_check_phone'))->toBeNull();
    });

    it('does not show the status after a wrong code', function () {
        checkStatus('2025550101');
        $this->otp->allows('verifyOTP')->andReturn(['success' => false, 'message' => 'Invalid OTP']);

        $this->post(route('public.application.status.verify.submit', $this->company->slug), ['otp' => '000000'])
            ->assertRedirect()
            ->assertDontSee('Jane');
    });

    it('does not show anything when phone and date of birth did not match, even with a valid code for that phone', function () {
        checkStatus('2025550101', '1991-01-01');

        $this->post(route('public.application.status.verify.submit', $this->company->slug), ['otp' => '123456'])
            ->assertRedirect(route('public.application.status', $this->company->slug))
            ->assertDontSee('Jane');
    });

    it('sends the code page back to the form when no check was started', function () {
        $this->get(route('public.application.status.verify', $this->company->slug))
            ->assertRedirect(route('public.application.status', $this->company->slug));

        $this->post(route('public.application.status.verify.submit', $this->company->slug), ['otp' => '123456'])
            ->assertRedirect(route('public.application.status', $this->company->slug));
    });

    it('does not carry a check started at another company', function () {
        $other = Applicant::company();
        checkStatus('2025550101');

        $this->post(route('public.application.status.verify.submit', $other->slug), ['otp' => '123456'])
            ->assertRedirect(route('public.application.status', $other->slug))
            ->assertDontSee('Jane');
    });
});

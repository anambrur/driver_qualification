<?php

use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| APP-04: the public OTP and lookup endpoints had no rate limit, and resend-otp texted
| whatever `phone` the request contained (SMS pumping / toll fraud). Limits are per IP:
|   - SMS-sending endpoints (send-otp, resend-otp, check-resume, check-status) share 5 per 10 minutes,
|   - code checks (verify-otp, check-resume-otp, status/verify) 10 per minute,
|   - the retired POST resume form 10 per 10 minutes.
*/

beforeEach(function () {
    $this->otp = Applicant::fakeOtp();
    $this->company = Applicant::company();
});

describe('APP-04: resend-otp only texts the phone verified in this session', function () {
    it('refuses to text a phone number given in the request when no OTP was started', function () {
        $this->otp->expects('resendOTP')->never();

        $this->postJson(route('public.application.resend.otp', $this->company->slug), ['phone' => '+12025550177'])
            ->assertStatus(400);
    });

    it('ignores a phone number in the request and texts the session phone', function () {
        $this->otp->expects('resendOTP')->once()->with('+12025550101')
            ->andReturn(['success' => true, 'method' => 'direct_sms']);

        $this->withSession(['otp_verification_phone' => '+12025550101'])
            ->postJson(route('public.application.resend.otp', $this->company->slug), ['phone' => '+12025550177'])
            ->assertOk();

        expect(session('otp_verification_phone'))->toBe('+12025550101');
    });
});

dataset('rate limited endpoints', [
    'send-otp' => ['public.application.send.otp', ['phone' => '2025550101', 'confirm_phone' => '2025550101'], 5],
    'resend-otp' => ['public.application.resend.otp', [], 5],
    'check-resume' => ['public.application.check.resume', ['phone' => '2025550101'], 5],
    'verify-otp' => ['public.application.submit.otp', ['otp' => '123456'], 10],
    'check-resume-otp' => ['public.application.verify.resume.otp', ['phone' => '2025550101', 'otp' => '123456'], 10],
    'resume' => ['public.application.verify.resume', ['phone' => '2025550101', 'date_of_birth' => '1990-01-01'], 10],
    'check-status' => ['public.application.check.status', ['phone' => '2025550101', 'date_of_birth' => '1990-01-01'], 5],
    'status/verify' => ['public.application.status.verify.submit', ['otp' => '123456'], 10],
]);

describe('APP-04: public OTP and lookup endpoints are rate limited per IP', function () {
    it('returns 429 once the limit is used up', function (string $route, array $payload, int $limit) {
        $url = route($route, $this->company->slug);

        for ($i = 0; $i < $limit; $i++) {
            expect($this->post($url, $payload)->status())->not->toBe(429);
        }

        $this->post($url, $payload)->assertStatus(429);
    })->with('rate limited endpoints');

    it('shares one SMS budget across send-otp, resend-otp, check-resume and check-status', function () {
        $slug = $this->company->slug;

        $this->post(route('public.application.send.otp', $slug), ['phone' => '2025550101', 'confirm_phone' => '2025550101']);
        $this->post(route('public.application.send.otp', $slug), ['phone' => '2025550102', 'confirm_phone' => '2025550102']);
        $this->post(route('public.application.resend.otp', $slug));
        $this->postJson(route('public.application.check.resume', $slug), ['phone' => '2025550103']);
        $this->post(route('public.application.check.status', $slug), ['phone' => '2025550104', 'date_of_birth' => '1990-01-01']);

        $this->postJson(route('public.application.check.resume', $slug), ['phone' => '2025550105'])->assertStatus(429);
    });

    it('counts each IP separately', function () {
        $url = route('public.application.send.otp', $this->company->slug);
        $payload = ['phone' => '2025550101', 'confirm_phone' => '2025550101'];

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->post($url, $payload);
        }

        expect($this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])->post($url, $payload)->status())->not->toBe(429);
    });
});

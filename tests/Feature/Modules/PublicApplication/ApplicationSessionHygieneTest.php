<?php

use App\Models\Driver;
use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| APP-09: `application_session_token` was written to the session but never checked (it never
| left the server, so it bound nothing), `save-progress` was a public route that answered an
| empty 200, and getOtpFromRequest() was unused. What the token seemed meant for, tying the
| application to the browser that proved the phone, is done by rotating the session id when
| the application session is granted (no session fixation).
*/

beforeEach(function () {
    $this->otp = Applicant::fakeOtp();
    $this->company = Applicant::company();
});

describe('APP-09: application session hygiene', function () {
    it('keeps the session id across requests that grant nothing (guards the assertions below)', function () {
        $this->withSession(['otp_verification_phone' => '+12025550101']);
        $before = session()->getId();

        $this->withCookie(config('session.cookie'), $before)
            ->get(route('public.application.verify.otp', $this->company->slug))
            ->assertOk();

        expect(session()->getId())->toBe($before);
    });

    it('no longer exposes the empty save-progress endpoint', function () {
        $status = $this->post("/{$this->company->slug}/application/save-progress")->status();

        expect($status)->toBeIn([404, 405]);
    });

    it('rotates the session id when the phone is verified', function () {
        $this->withSession(['otp_verification_phone' => '+12025550101']);
        $before = session()->getId();

        $this->withCookie(config('session.cookie'), $before)
            ->post(route('public.application.submit.otp', $this->company->slug), ['otp' => '123456'])
            ->assertRedirect(route('public.application.step1', $this->company->slug));

        $driver = Driver::query()->where('main_phone', '+12025550101')->firstOrFail();

        expect(session()->getId())->not->toBe($before)
            ->and(session('application_driver_id'))->toBe($driver->id)
            ->and(session()->has('application_session_token'))->toBeFalse();
    });

    it('rotates the session id when an application is resumed by SMS code', function () {
        $driver = Applicant::draft($this->company, ['main_phone' => '+12025550101']);
        $this->withSession([]);
        $before = session()->getId();

        $this->withCookie(config('session.cookie'), $before)
            ->postJson(route('public.application.verify.resume.otp', $this->company->slug), [
                'phone' => '2025550101',
                'otp' => '123456',
            ])->assertJson(['success' => true]);

        expect(session()->getId())->not->toBe($before)
            ->and(session('application_driver_id'))->toBe($driver->id)
            ->and(session()->has('application_session_token'))->toBeFalse();
    });
});

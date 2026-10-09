<?php

use App\Models\Driver;
use App\Models\DriverDocument;
use Database\Factories\CompanyFactory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| APP-08: only show() and start() checked `companies.status = active`. An inactive company's
| application URLs still sent SMS codes, created drivers and saved documents.
*/

beforeEach(function () {
    Storage::fake('public');
    Mail::fake();
    $this->otp = Applicant::fakeOtp();
    $this->company = CompanyFactory::new()->inactive()->create();
});

describe('APP-08: an inactive company accepts no applications', function () {
    it('does not show the landing or start page', function () {
        $this->get(route('application.form', $this->company->slug))->assertNotFound();
        $this->get(route('public.application.start', $this->company->slug))->assertNotFound();
    });

    it('does not send an SMS code', function () {
        $this->otp->expects('sendOTP')->never();

        $this->post(route('public.application.send.otp', $this->company->slug), [
            'phone' => '2025550101',
            'confirm_phone' => '2025550101',
        ])->assertNotFound();
    });

    it('does not create a draft driver on OTP verification', function () {
        $this->withSession(['otp_verification_phone' => '+12025550101'])
            ->post(route('public.application.submit.otp', $this->company->slug), ['otp' => '123456'])
            ->assertNotFound();

        expect(Driver::query()->where('company_id', $this->company->id)->exists())->toBeFalse();
    });

    it('does not save application steps, even with a session started while it was active', function (int $step) {
        $driver = Applicant::draft($this->company);

        $this->withSession(Applicant::session($driver))
            ->post(route("public.application.store.step{$step}", $this->company->slug), Applicant::stepPayload($step, $driver))
            ->assertNotFound();

        expect(DriverDocument::query()->where('driver_id', $driver->id)->exists())->toBeFalse()
            ->and($driver->fresh()->status)->toBe('draft');
    })->with([2, 7, 10]);

    it('does not save step 1', function () {
        $driver = Applicant::draft($this->company);

        $this->withSession(Applicant::session($driver))
            ->post(route('public.application.store.step1', $this->company->slug), Applicant::step1Payload($driver))
            ->assertNotFound();

        expect($driver->fresh()->first_name)->toBeNull();
    });

    it('does not render application steps', function () {
        $driver = Applicant::draft($this->company);

        $this->withSession(Applicant::session($driver))
            ->get(route('public.application.step2', ['slug' => $this->company->slug, 'driver_id' => $driver->id]))
            ->assertNotFound();
    });

    it('does not send a resume code', function () {
        Applicant::draft($this->company, ['main_phone' => '+12025550101']);
        $this->otp->expects('sendOTP')->never();

        $this->postJson(route('public.application.check.resume', $this->company->slug), ['phone' => '2025550101'])
            ->assertJson(['success' => false]);
    });

    it('does not resend an SMS code', function () {
        $this->otp->expects('resendOTP')->never();

        $this->withSession(['otp_verification_phone' => '+12025550101'])
            ->postJson(route('public.application.resend.otp', $this->company->slug))
            ->assertNotFound();
    });
});

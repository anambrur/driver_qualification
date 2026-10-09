<?php

use App\Models\Driver;
use App\Models\DriverDocument;
use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| APP-06: withdraw() wrote `withdrawn_at` (no such column) and status `withdrawn` (not in the
| enum), so it always failed, and no page linked to it.
| Product decision (2026-10-09): add a `withdrawn` status, and let a withdrawn applicant apply
| again. Applying again goes through the normal phone + SMS start and reopens their old record
| as a draft (drivers.email is unique per company, so a second record with the same email
| can't exist at the same company).
*/

beforeEach(function () {
    $this->otp = Applicant::fakeOtp();
});

describe('APP-06: withdrawing an application', function () {
    it('withdraws the applicant\'s own application and ends their session', function () {
        $me = Applicant::draft();
        $slug = $me->company->slug;

        $this->withSession(Applicant::session($me))
            ->post(route('public.application.withdraw', ['slug' => $slug, 'driver_id' => $me->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('public.application.start', $slug));

        $me->refresh();
        expect($me->status)->toBe('withdrawn')
            ->and($me->withdrawn_at)->not->toBeNull()
            ->and(session('application_driver_id'))->toBeNull()
            ->and(session('verified_phone'))->toBeNull();

        $this->get(route('public.application.step2', ['slug' => $slug, 'driver_id' => $me->id]))
            ->assertRedirect(route('public.application.start', $slug));
    });

    it('withdraws a submitted (pending) application resumed by SMS code', function () {
        $me = Applicant::draft(attributes: ['status' => 'pending']);

        $this->withSession(Applicant::session($me))
            ->post(route('public.application.withdraw', ['slug' => $me->company->slug, 'driver_id' => $me->id]));

        expect($me->fresh()->status)->toBe('withdrawn');
    });

    it('shows a withdraw button on the step pages', function (int $step) {
        $me = Applicant::draft();
        DriverDocument::query()->create(['driver_id' => $me->id, 'license_front' => 'images/documents/f.jpg']);
        $url = $step === 1
            ? route('public.application.step1', $me->company->slug)
            : route("public.application.step{$step}", ['slug' => $me->company->slug, 'driver_id' => $me->id]);

        $this->withSession(Applicant::session($me))
            ->get($url)
            ->assertOk()
            ->assertSee(route('public.application.withdraw', ['slug' => $me->company->slug, 'driver_id' => $me->id]), false)
            ->assertSee('Withdraw application');
    })->with([1, 2, 5, 10]);

    it('does not let an applicant resume a withdrawn application', function () {
        $me = Applicant::draft(attributes: ['main_phone' => '+12025550101', 'status' => 'withdrawn']);

        $this->postJson(route('public.application.verify.resume.otp', $me->company->slug), ['phone' => '2025550101', 'otp' => '123456'])
            ->assertJson(['success' => false]);

        expect(session('application_driver_id'))->toBeNull();
    });
});

describe('APP-06: a withdrawn applicant can apply again', function () {
    it('lets a withdrawn applicant request a new SMS code', function () {
        $me = Applicant::draft(attributes: ['main_phone' => '+12025550101', 'status' => 'withdrawn']);
        $this->otp->expects('sendOTP')->once()->andReturn(['success' => true, 'method' => 'direct_sms']);

        $this->post(route('public.application.send.otp', $me->company->slug), [
            'phone' => '2025550101',
            'confirm_phone' => '2025550101',
        ])->assertRedirect(route('public.application.verify.otp', $me->company->slug));
    });

    it('reopens the withdrawn application as a draft after the SMS code is verified', function () {
        $me = Applicant::draft(attributes: [
            'main_phone' => '+12025550101',
            'email' => 'jane.roe@example.com',
            'status' => 'withdrawn',
            'withdrawn_at' => now()->subDay(),
        ]);
        $slug = $me->company->slug;

        $this->withSession(['otp_verification_phone' => '+12025550101'])
            ->post(route('public.application.submit.otp', $slug), ['otp' => '123456'])
            ->assertRedirect(route('public.application.step1', $slug));

        $me->refresh();
        expect($me->status)->toBe('draft')
            ->and($me->withdrawn_at)->toBeNull()
            ->and(session('application_driver_id'))->toBe($me->id)
            ->and(Driver::query()->where('company_id', $me->company_id)->count())->toBe(1);

        // …and can complete step 1 again with the same email.
        $this->post(route('public.application.store.step1', $slug), Applicant::step1Payload($me))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('public.application.step2', ['slug' => $slug, 'driver_id' => $me->id]));
    });

    it('prefers an open draft over a withdrawn application for the same phone', function () {
        $company = Applicant::company();
        Applicant::draft($company, ['main_phone' => '+12025550101', 'status' => 'withdrawn']);
        $draft = Applicant::draft($company, ['main_phone' => '+12025550101']);

        $this->withSession(['otp_verification_phone' => '+12025550101'])
            ->post(route('public.application.submit.otp', $company->slug), ['otp' => '123456']);

        expect(session('application_driver_id'))->toBe($draft->id);
    });
});

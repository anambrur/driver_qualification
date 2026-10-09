<?php

use Database\Factories\CompanyFactory;
use Database\Factories\DriverFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| APP-07: drivers.email was unique across all companies, so a driver who applied to (or works
| for) company A couldn't finish step 1 at company B: the insert failed, the error was
| swallowed, and the applicant saw "Failed to save information".
| Product decision (2026-10-09): email is unique per company.
*/

beforeEach(function () {
    Storage::fake('public');
    Applicant::fakeOtp();
});

describe('APP-07: a driver email is unique per company', function () {
    it('lets a driver who applied to company A apply to company B with the same email', function () {
        DriverFactory::new()->create(['email' => 'jane.roe@example.com']);
        $me = Applicant::draft();

        $this->withSession(Applicant::session($me))
            ->post(route('public.application.store.step1', $me->company->slug), Applicant::step1Payload($me))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('public.application.step2', ['slug' => $me->company->slug, 'driver_id' => $me->id]));

        expect($me->fresh()->email)->toBe('jane.roe@example.com');
    });

    it('explains, instead of failing silently, when the email is taken at the same company', function () {
        $company = Applicant::company();
        DriverFactory::new()->forCompany($company)->create(['email' => 'jane.roe@example.com']);
        $me = Applicant::draft($company);

        $this->withSession(Applicant::session($me))
            ->from(route('public.application.step1', $company->slug))
            ->post(route('public.application.store.step1', $company->slug), Applicant::step1Payload($me))
            ->assertRedirect(route('public.application.step1', $company->slug))
            ->assertSessionHasErrors(['email' => 'This email is already used by another application at this company.']);

        expect($me->fresh()->email)->toBeNull();
    });

    it('lets the applicant save step 1 again with their own email', function () {
        $me = Applicant::draft(attributes: ['email' => 'jane.roe@example.com']);

        $this->withSession(Applicant::session($me))
            ->post(route('public.application.store.step1', $me->company->slug), Applicant::step1Payload($me))
            ->assertSessionHasNoErrors();
    });

    it('enforces it in the database: same email in two companies, never twice in one', function () {
        $company = CompanyFactory::new()->create();
        DriverFactory::new()->create(['email' => 'shared@example.com']);
        DriverFactory::new()->forCompany($company)->create(['email' => 'shared@example.com']);

        expect(fn () => DriverFactory::new()->forCompany($company)->create(['email' => 'shared@example.com']))
            ->toThrow(QueryException::class);
    });
});

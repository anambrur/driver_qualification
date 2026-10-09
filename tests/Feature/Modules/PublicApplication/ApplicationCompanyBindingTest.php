<?php

use App\Http\Requests\PublicApplication\StoreApplicationFmcsaConsentRequest;
use App\Models\DriverDocument;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| APP-02: the store requests validated driver_id with a bare `exists:drivers,id`, and nothing
| tied the session or the driver to the {slug} company. A session (stale or tampered) that
| points at company A's driver must not be usable under company B's URL, and driver_id must
| belong to the {slug} company.
*/

beforeEach(function () {
    Storage::fake('public');
    Mail::fake();
    Applicant::fakeOtp();
});

describe('APP-02: the application session and driver_id are bound to the {slug} company', function () {
    it('does not accept a session verified for another company', function (int $step) {
        $companyA = Applicant::company();
        $companyB = Applicant::company();
        $driverA = Applicant::draft($companyA);

        $this->withSession(Applicant::session($driverA))
            ->post(route("public.application.store.step{$step}", $companyB->slug), Applicant::stepPayload($step, $driverA))
            ->assertRedirect();

        // Rejected either by the scoped driver_id rule or by the session check; never saved.
        expect(DriverDocument::query()->where('driver_id', $driverA->id)->exists())->toBeFalse()
            ->and($driverA->fresh()->status)->toBe('draft');
    })->with([2, 5, 7, 10]);

    it('rejects a session verified for another company even when driver_id passes validation', function () {
        $companyA = Applicant::company();
        $companyB = Applicant::company();
        $driverA = Applicant::draft($companyA);
        $driverB = Applicant::draft($companyB);

        // Session belongs to company A; driver_id is a real company B driver.
        $this->withSession(Applicant::session($driverA))
            ->post(route('public.application.store.step7', $companyB->slug), Applicant::stepPayload(7, $driverB))
            ->assertRedirect(route('public.application.start', $companyB->slug));

        expect(DriverDocument::query()->whereIn('driver_id', [$driverA->id, $driverB->id])->exists())->toBeFalse();
    });

    it('does not write to a driver of another company even if the session claims the slug', function (int $step) {
        $companyA = Applicant::company();
        $companyB = Applicant::company();
        $driverA = Applicant::draft($companyA);

        // Session says "company B" but its driver belongs to company A.
        $session = array_merge(Applicant::session($driverA, $companyB->slug), ['verified_company_id' => $companyB->id]);

        $this->withSession($session)
            ->post(route("public.application.store.step{$step}", $companyB->slug), Applicant::stepPayload($step, $driverA))
            ->assertRedirect();

        expect(DriverDocument::query()->where('driver_id', $driverA->id)->exists())->toBeFalse()
            ->and($driverA->fresh()->status)->toBe('draft');
    })->with([2, 5, 7, 10]);

    it('does not render a driver of another company under this company\'s URL', function () {
        $companyA = Applicant::company();
        $companyB = Applicant::company();
        $driverA = Applicant::draft($companyA, ['first_name' => 'Victoria']);
        $session = array_merge(Applicant::session($driverA, $companyB->slug), ['verified_company_id' => $companyB->id]);

        $this->withSession($session)
            ->get(route('public.application.step2', ['slug' => $companyB->slug, 'driver_id' => $driverA->id]))
            ->assertRedirect(route('public.application.start', $companyB->slug));
    });

    it('does not render step 1 for a driver of another company', function () {
        $companyA = Applicant::company();
        $companyB = Applicant::company();
        $driverA = Applicant::draft($companyA);
        $session = array_merge(Applicant::session($driverA, $companyB->slug), ['verified_company_id' => $companyB->id]);

        $this->withSession($session)
            ->get(route('public.application.step1', $companyB->slug))
            ->assertRedirect(route('public.application.start', $companyB->slug));
    });

    it('validates driver_id against the {slug} company, not any driver', function () {
        $companyA = Applicant::company();
        $companyB = Applicant::company();
        $driverA = Applicant::draft($companyA);

        $request = StoreApplicationFmcsaConsentRequest::create("/{$companyB->slug}/application/step-7", 'POST');
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        $validator = Validator::make(
            ['driver_id' => $driverA->id, 'employee_signature' => 'x', 'consent_agreement' => '1', 'date_signed' => now()->toDateString()],
            $request->rules(),
        );

        expect($validator->errors()->has('driver_id'))->toBeTrue();
    });
});

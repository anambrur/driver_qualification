<?php

use App\Models\DriverDocument;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\PublicApplication\Applicant;

/*
| APP-01: checkApplicationSession() returned a redirect that every caller ignored, so an
| anonymous visitor could read any applicant's PII (GET step-N/{driver_id}) and overwrite
| their documents and signatures (POST step-N with driver_id) just by guessing an id.
*/

beforeEach(function () {
    Storage::fake('public');
    Mail::fake();
    Applicant::fakeOtp();
});

dataset('store steps', [2, 3, 4, 5, 6, 7, 8, 9, 10]);

describe('APP-01: the step pages require the applicant session', function () {
    it('does not let an anonymous visitor overwrite an applicant\'s FMCSA consent', function () {
        $victim = Applicant::draft();
        DriverDocument::query()->create([
            'driver_id' => $victim->id,
            'fmcsa_consent_signature' => 'Real Applicant',
        ]);

        $this->post(route('public.application.store.step7', $victim->company->slug), Applicant::stepPayload(7, $victim))
            ->assertRedirect(route('public.application.start', $victim->company->slug));

        expect(DriverDocument::query()->where('driver_id', $victim->id)->value('fmcsa_consent_signature'))
            ->toBe('Real Applicant');
    });

    it('rejects an anonymous POST to every store step', function (int $step) {
        $victim = Applicant::draft();
        $slug = $victim->company->slug;

        $this->post(route("public.application.store.step{$step}", $slug), Applicant::stepPayload($step, $victim))
            ->assertRedirect(route('public.application.start', $slug));

        expect(DriverDocument::query()->where('driver_id', $victim->id)->exists())->toBeFalse()
            ->and($victim->fresh()->status)->toBe('draft');
        Storage::disk('public')->assertDirectoryEmpty('images/documents');
    })->with('store steps');

    it('does not render another applicant\'s step page to an anonymous visitor', function (int $step) {
        $victim = Applicant::draft(attributes: ['first_name' => 'Victoria', 'last_name' => 'Secretname']);
        $slug = $victim->company->slug;

        $this->get(route("public.application.step{$step}", ['slug' => $slug, 'driver_id' => $victim->id]))
            ->assertRedirect(route('public.application.start', $slug));
    })->with('store steps');

    it('does not render step 1 without a session', function () {
        $company = Applicant::company();

        $this->get(route('public.application.step1', $company->slug))
            ->assertRedirect(route('public.application.start', $company->slug));
    });

    it('does not let an applicant write to another applicant\'s record at the same company', function (int $step) {
        $company = Applicant::company();
        $me = Applicant::draft($company);
        $victim = Applicant::draft($company);

        $this->withSession(Applicant::session($me))
            ->post(route("public.application.store.step{$step}", $company->slug), Applicant::stepPayload($step, $victim))
            ->assertRedirect(route('public.application.start', $company->slug));

        expect(DriverDocument::query()->where('driver_id', $victim->id)->exists())->toBeFalse()
            ->and($victim->fresh()->status)->toBe('draft');
    })->with('store steps');

    it('does not show another applicant\'s step page to an applicant', function () {
        $company = Applicant::company();
        $me = Applicant::draft($company);
        $victim = Applicant::draft($company, ['first_name' => 'Victoria']);

        $this->withSession(Applicant::session($me))
            ->get(route('public.application.step2', ['slug' => $company->slug, 'driver_id' => $victim->id]))
            ->assertRedirect(route('public.application.start', $company->slug));
    });

    it('does not let an anonymous visitor withdraw an application', function () {
        $victim = Applicant::draft();

        $this->post(route('public.application.withdraw', ['slug' => $victim->company->slug, 'driver_id' => $victim->id]))
            ->assertRedirect(route('public.application.start', $victim->company->slug));

        expect($victim->fresh()->status)->toBe('draft');
    });
});

describe('APP-01: the applicant can still complete their own application', function () {
    it('renders every step page for the session applicant', function (int $step) {
        $me = Applicant::draft(attributes: ['first_name' => 'Jane']);
        // Step 2 always creates the document row before later steps are reachable.
        DriverDocument::query()->create(['driver_id' => $me->id, 'license_front' => 'images/documents/f.jpg']);

        $this->withSession(Applicant::session($me))
            ->get(route("public.application.step{$step}", ['slug' => $me->company->slug, 'driver_id' => $me->id]))
            ->assertOk();
    })->with('store steps');

    it('renders step 1 for the session applicant', function () {
        $me = Applicant::draft();

        $this->withSession(Applicant::session($me))
            ->get(route('public.application.step1', $me->company->slug))
            ->assertOk();
    });

    it('saves every step for the session applicant', function (int $step) {
        $me = Applicant::draft(attributes: ['email' => 'me@example.com']);
        $slug = $me->company->slug;

        $response = $this->withSession(Applicant::session($me))
            ->post(route("public.application.store.step{$step}", $slug), Applicant::stepPayload($step, $me));

        $expected = $step === 10
            ? route('public.application.complete', $slug)
            : route('public.application.step'.($step + 1), ['slug' => $slug, 'driver_id' => $me->id]);

        $response->assertSessionHasNoErrors()->assertRedirect($expected);
        expect(DriverDocument::query()->where('driver_id', $me->id)->exists())->toBeTrue();

        if ($step === 10) {
            expect($me->fresh()->status)->toBe('pending');
        }
    })->with('store steps');

    it('saves step 1 for the session applicant', function () {
        $me = Applicant::draft();

        $this->withSession(Applicant::session($me))
            ->post(route('public.application.store.step1', $me->company->slug), Applicant::step1Payload($me))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('public.application.step2', ['slug' => $me->company->slug, 'driver_id' => $me->id]));

        expect($me->fresh()->first_name)->toBe('Jane');
    });
});

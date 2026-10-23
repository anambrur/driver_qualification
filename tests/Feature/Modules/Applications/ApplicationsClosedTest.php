<?php

use App\Features\Feature;
use App\Services\Billing\AccessStateResolver;
use Tests\Support\Actors;

/*
| Applications closed (Phase 0 gap, built 23 Oct 2026): without driver_applications on, the public
| apply link and the application steps show a closed page; past applicants can still check their
| status. A read-only or unsubscribed company has the feature off or read-only, so it is closed too.
*/

it('opens the apply link when the plan includes online applications', function () {
    $company = Actors::company();

    $this->get(route('application.form', $company->slug))->assertOk()->assertDontSee('Online applications are closed');
    $this->get(route('public.application.start', $company->slug))->assertOk();
});

it('closes the apply link and the steps when the plan does not include them', function () {
    $company = Actors::withPlan(Actors::company(), [Feature::MaxDrivers->value]);

    $this->get(route('application.form', $company->slug))->assertForbidden()
        ->assertSee('Online applications are closed')->assertSee($company->company_name);
    $this->get(route('public.application.start', $company->slug))->assertForbidden();
    $this->post(route('public.application.send.otp', $company->slug), ['phone' => '+15555550100', 'confirm_phone' => '+15555550100'])->assertForbidden();
});

it('keeps the status check open for past applicants', function () {
    $company = Actors::withPlan(Actors::company(), [Feature::MaxDrivers->value]);

    $this->get(route('public.application.status', $company->slug))->assertOk();
});

it('closes the apply link for a read-only or unsubscribed company', function () {
    $readOnly = Actors::readOnly(Actors::company());
    $unsubscribed = Actors::company([], subscribed: false);
    app(AccessStateResolver::class)->forget($readOnly->id);

    $this->get(route('application.form', $readOnly->slug))->assertForbidden();
    $this->get(route('application.form', $unsubscribed->slug))->assertForbidden();
});

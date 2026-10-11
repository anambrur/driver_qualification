<?php

use Database\Factories\DriverFactory;
use Tests\Support\Actors;

/*
| SUB-04: /dashboard (auth+verified) had no `Subscribed` gate while /admin/dashboard did.
| Decided PO-16 (2026-10-11): /dashboard is gated too, so new and lapsed accounts land on
| pricing. Super-admins pass `Subscribed`.
*/

it('sends an expired tenant from /dashboard to pricing (decided PO-16, 2026-10-11)', function () {
    $user = Actors::expiredCompanyOwner();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('pricing.plans'));
    $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('pricing.plans'));
});

it('sends a new account with no plan to pricing', function () {
    $user = Actors::companyOwner(subscribed: false);
    DriverFactory::new()->create(['company_id' => $user->company->id]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('pricing.plans'));
    $this->actingAs($user)->get(route('pricing.plans'))->assertOk();
});

it('still shows a subscribed tenant only their own company counts', function () {
    $user = Actors::companyOwner();
    DriverFactory::new()->create(['company_id' => $user->company->id]);
    DriverFactory::new()->count(3)->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('driverStats', fn (array $stats) => $stats['total'] === 1)
        ->assertViewHas('companyStats', null);
});

it('lets a super-admin open /dashboard without a subscription', function () {
    $this->actingAs(Actors::superAdmin())->get(route('dashboard'))->assertOk();
});

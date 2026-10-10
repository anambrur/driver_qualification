<?php

use Database\Factories\DriverFactory;
use Tests\Support\Actors;

/*
| SUB-04: /dashboard (auth+verified) has no `Subscribed` gate while /admin/dashboard does.
| Whether unsubscribed tenants should see /dashboard is a product question (DEFERRED: login,
| registration and email verification all land there). These tests pin what it exposes today:
| own-company aggregate counts only, never another tenant's data or the global company stats.
*/

it('currently lets an expired tenant open /dashboard (DEFERRED SUB-04)', function () {
    $user = Actors::expiredCompanyOwner();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->actingAs($user)->get(route('admin.dashboard'))->assertRedirect(route('pricing.plans'));
});

it('shows an unsubscribed tenant only their own company counts', function () {
    $user = Actors::companyOwner(subscribed: false);
    DriverFactory::new()->create(['company_id' => $user->company->id]);
    DriverFactory::new()->count(3)->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('driverStats', fn (array $stats) => $stats['total'] === 1)
        ->assertViewHas('companyStats', null);
});

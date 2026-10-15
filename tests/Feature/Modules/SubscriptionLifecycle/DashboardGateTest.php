<?php

use App\Services\Billing\AccessStateResolver;
use Database\Factories\DriverFactory;
use Database\Factories\SubscriptionFactory;
use Tests\Support\Actors;

/*
| SUB-04 / PO-16 and 07 §4.3, §8: tenant pages need an access state. A company that never had
| a subscription goes to pricing; a lapsed one is read-only (decided Q2): pages open, writes are
| denied, billing stays writable so it can pay. Platform staff have no tenant session.
*/

function actingWithFreshState($test, $user)
{
    app(AccessStateResolver::class)->forget($user->company_id);

    return $test->actingAs($user);
}

it('sends a new account with no plan to pricing', function () {
    $company = Actors::company(subscribed: false);
    DriverFactory::new()->forCompany($company)->create();

    $this->actingAs($company->owner)->get(route('dashboard'))->assertRedirect(route('pricing.plans'));
    $this->actingAs($company->owner)->get(route('profile.edit'))->assertRedirect(route('pricing.plans'));
    $this->actingAs($company->owner)->get(route('pricing.plans'))->assertOk();
    $this->actingAs($company->owner)->get(route('billing.index'))->assertOk();
});

it('answers 402 to a JSON request without a subscription', function () {
    $this->actingAs(Actors::company(subscribed: false)->owner)
        ->getJson(route('profile.edit'))
        ->assertStatus(402)
        ->assertJson(['access_state' => 'none']);
});

it('keeps a lapsed company in read-only: pages open, writes denied, billing open', function () {
    $company = Actors::readOnly(Actors::company());
    $owner = $company->owner;

    actingWithFreshState($this, $owner)->get(route('profile.edit'))->assertOk();
    actingWithFreshState($this, $owner)->get(route('users.index'))->assertOk()->assertSee('subscription has ended');
    actingWithFreshState($this, $owner)->get(route('users.invite'))->assertForbidden();
    actingWithFreshState($this, $owner)->get(route('pricing.plans'))->assertOk();
    actingWithFreshState($this, $owner)->get(route('billing.index'))->assertOk();
});

it('denies write permissions but keeps view and billing permissions when read-only', function () {
    $owner = Actors::readOnly(Actors::company())->owner;
    Actors::actingAsTenant($owner);

    expect($owner->can('users.view'))->toBeTrue()
        ->and($owner->can('users.create'))->toBeFalse()
        ->and($owner->can('drivers.create'))->toBeFalse()
        ->and($owner->can('billing.manage'))->toBeTrue();
});

it('keeps full access with a banner during the past-due grace', function () {
    $company = Actors::company(subscribed: false);
    SubscriptionFactory::new()->forCompany($company)->pastDue()->create(['current_period_start' => now()->subDays(2)]);
    $owner = $company->owner;

    actingWithFreshState($this, $owner)->get(route('users.index'))->assertOk()->assertSee('last payment failed');
    actingWithFreshState($this, $owner)->get(route('users.invite'))->assertOk();
});

it('sends an expired tenant from /dashboard to pricing only when it never paid', function () {
    $company = Actors::company(subscribed: false);

    $this->actingAs($company->owner)->get(route('dashboard'))->assertRedirect(route('pricing.plans'));
    $this->actingAs($company->owner)->get(route('admin.dashboard'))->assertRedirect(route('pricing.plans'));
});

it('still shows a subscribed tenant only their own company counts', function () {
    $user = Actors::companyOwner();
    DriverFactory::new()->create(['company_id' => $user->company->id]);
    DriverFactory::new()->count(3)->create();

    $response = $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('driverStats', fn (array $stats) => $stats['total'] === 1);
    // Cross-company stats are the platform dashboard's (WP8a)
    expect($response->original->getData())->not->toHaveKey('companyStats');
});

it('does not let a platform user open /dashboard (no company, no tenant access)', function () {
    $this->actingAs(Actors::platformOwner(), 'platform')->get(route('dashboard'))->assertRedirect();
});

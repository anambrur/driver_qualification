<?php

use Database\Factories\PlanFactory;
use Database\Factories\PlanPriceFactory;
use Spatie\Permission\Models\Role;
use Tests\Support\Actors;

/*
| UI-09 (phase 2, batch 4a-2): the platform forms and the company page on x-ui fields and cards,
| with a confirm step on destructive actions. Field names, routes and permissions are unchanged.
*/

it('renders each form on x-ui fields with the shared confirm dialog', function (Closure $url) {
    $this->actingAs(Actors::platformOwner(), 'platform');

    $this->get($url())
        ->assertOk()
        ->assertSee('class="field', false)
        ->assertSee('id="confirm-dialog"', false);
})->with([
    'company create' => [fn () => route('platform.companies.create')],
    'company edit' => [fn () => route('platform.companies.edit', Actors::company())],
    'plan create' => [fn () => route('platform.plans.create')],
    'plan edit' => [fn () => route('platform.plans.edit', PlanFactory::new()->create())],
    'staff create' => [fn () => route('platform.staff.create')],
    'staff edit' => [fn () => route('platform.staff.edit', Actors::platformSupport())],
    'asset transfer' => [fn () => route('platform.asset-transfers.create')],
    'support request' => [fn () => route('platform.support-sessions.create')],
    'company page' => [fn () => route('platform.companies.show', Actors::company())],
]);

it('asks before deleting a plan or archiving a price', function () {
    $plan = PlanFactory::new()->create();
    PlanPriceFactory::new()->for($plan)->create();

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.plans.edit', $plan))
        ->assertOk()
        ->assertSee('data-confirm="Delete '.e($plan->name).'?"', false)
        ->assertSee('data-confirm="Archive this price?"', false)
        ->assertDontSee('onsubmit="return confirm(', false);
});

it('asks before expiring, changing status or transferring ownership on the company page', function () {
    $company = Actors::company();

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.companies.show', $company))
        ->assertOk()
        ->assertSee('data-confirm="Expire the subscription now?"', false)
        ->assertSee('data-confirm="Transfer ownership?"', false)
        ->assertSee('data-confirm-button="Change status"', false);
});

it('shows support staff the company page without its actions', function () {
    $company = Actors::company();

    $this->actingAs(Actors::platformSupport(), 'platform')
        ->get(route('platform.companies.show', $company))
        ->assertOk()
        ->assertSee($company->company_name)
        ->assertDontSee(route('platform.companies.edit', $company))
        ->assertDontSee(route('platform.companies.subscription.grant', $company))
        ->assertDontSee(route('platform.companies.status', $company))
        ->assertDontSee(route('platform.companies.overrides.store', $company));
});

it('escapes the company name inside the confirm text', function () {
    $company = Actors::company(['company_name' => 'Evil" onmouseover="alert(1)']);

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.companies.show', $company))
        ->assertOk()
        ->assertDontSee('Evil" onmouseover="alert(1)', false)
        ->assertSee(e('Evil" onmouseover="alert(1)'), false);
});

it('keeps the chosen roles after a failed staff save', function () {
    $owner = Actors::platformOwner();
    $auditor = Role::query()->where('team_id', 0)->where('system_key', 'auditor')->firstOrFail();

    $this->actingAs($owner, 'platform')
        ->from(route('platform.staff.create'))
        ->post(route('platform.staff.store'), ['name' => '', 'email' => 'new@staff.test', 'roles' => [$auditor->id]])
        ->assertSessionHasErrors('name');

    $this->get(route('platform.staff.create'))
        ->assertOk()
        ->assertSee('id="role-'.$auditor->id.'" value="'.$auditor->id.'" class="input mt-0.5" checked', false);
});

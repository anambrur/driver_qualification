<?php

use App\Models\AuditLog;
use Database\Factories\PlanFactory;
use Database\Factories\PlanPriceFactory;
use Tests\Support\Actors;

/*
| UI-10 (phase 2, batch 4a-3): the small platform lists, support sessions, both dashboards and the
| audit log on x-ui tables and cards. The audit log keeps cursor paging (no COUNT on audit_logs).
*/

it('renders each page on x-ui cards', function (string $route) {
    PlanFactory::new()->create();

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route($route))
        ->assertOk()
        ->assertSee('class="card', false)
        ->assertDontSee('rounded-2xl border border-gray-200', false);
})->with(['platform.dashboard', 'platform.plans.index', 'platform.staff.index', 'platform.features.index',
    'platform.support-sessions.index', 'platform.subscriptions.dashboard', 'platform.audit.index']);

it('shows the company and subscription counts as stat cards', function () {
    Actors::company();
    Actors::suspended(Actors::company());

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Active', '1', 'Suspended', '1', 'Deactivated', '0']);
});

it('links the dashboard stats only for staff who can open the list', function () {
    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.dashboard'))->assertSee('href="'.route('platform.companies.index').'"', false);

    $this->actingAs(Actors::platformStaff('billing_manager'), 'platform')
        ->get(route('platform.subscriptions.dashboard'))
        ->assertOk()
        ->assertSee(route('platform.subscriptions.payments'));

    $this->actingAs(Actors::platformSupport(), 'platform')
        ->get(route('platform.subscriptions.dashboard'))
        ->assertOk()
        ->assertDontSee(route('platform.subscriptions.payments'));
});

it('lists plans with their current prices only', function () {
    $plan = PlanFactory::new()->create(['name' => 'Fleet Pro']);
    PlanPriceFactory::new()->for($plan)->create(['amount' => 99]);
    PlanPriceFactory::new()->for($plan)->yearly(990)->create(['archived_at' => now()]);

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.plans.index'))
        ->assertOk()
        ->assertSee('Fleet Pro')
        ->assertSee('99.00 USD / month')
        ->assertDontSee('990.00 USD / year');
});

it('asks before disabling a staff member, and offers no actions on yourself', function () {
    $owner = Actors::platformOwner();
    $other = Actors::platformSupport(['name' => 'Sam Support']);

    $this->actingAs($owner, 'platform')
        ->get(route('platform.staff.index'))
        ->assertOk()
        ->assertSee('data-confirm="Disable Sam Support?"', false)
        ->assertSee(route('platform.staff.edit', $other))
        ->assertDontSee(route('platform.staff.edit', $owner));
});

it('pages the audit log with a cursor and keeps the filters', function () {
    foreach (range(1, 51) as $i) {
        AuditLog::query()->forceCreate(['actor_type' => 'system', 'action' => 'company.test_'.$i, 'created_at' => now()]);
    }

    $html = $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.audit.index', ['action' => 'company.']))
        ->assertOk()
        ->assertSee('company.test_51')
        ->assertDontSee('company.test_1<', false)
        ->getContent();

    expect($html)->toContain('rel="next"')->toContain('action=company.')->toContain('cursor=')
        ->and($html)->not->toContain('rel="prev"');
});

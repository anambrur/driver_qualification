<?php

use App\Models\AssetGroup;
use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\User;
use Database\Factories\AssetGroupFactory;
use Database\Factories\DriverFactory;
use Tests\Support\Actors;

/*
| Terminate (Phase 0 gap, built 23 Oct 2026): POST admin.driver.hire-status with action
| terminate (permission:drivers.hire). Ends employment, switches off the driver's login and
| ends its active asset groups; the DQ file is kept (49 CFR 391.51).
*/

function terminatePayload(array $overrides = []): array
{
    return array_merge(['action' => 'terminate', 'termination_date' => now()->toDateString(), 'termination_reason' => 'Left for another carrier'], $overrides);
}

it('terminates an active driver, switches off the login and ends the asset groups', function () {
    $company = Actors::company();
    $owner = Actors::companyOwner($company);
    $login = Actors::driverUser($company);
    $driver = Driver::withoutTenancy()->where('user_id', $login->id)->sole();
    $driver->forceFill(['hire_date' => now()->subYear()])->save();
    $group = AssetGroupFactory::new()->forCompany($company)->create(['driver_id' => $driver->id]);

    $this->actingAs($owner)
        ->postJson(route('admin.driver.hire-status', $driver->id), terminatePayload())
        ->assertOk()->assertJsonPath('data.status', 'terminated');

    $driver->refresh();
    expect($driver->employment_status)->toBe('terminated')
        ->and($driver->termination_date->toDateString())->toBe(now()->toDateString())
        ->and($driver->termination_reason)->toBe('Left for another carrier')
        ->and($driver->terminated_at)->not->toBeNull()
        ->and(User::query()->find($login->id)->status)->toBe('inactive')
        ->and(AssetGroup::withoutTenancy()->find($group->id)->status)->toBe('inactive')
        ->and(AuditLog::query()->where('action', 'driver.terminated')->value('changes'))->toMatchArray(['login_disabled' => true, 'asset_groups_ended' => 1]);
});

it('logs the terminated driver out on the next request', function () {
    $company = Actors::company();
    $login = Actors::driverUser($company);
    $driver = Driver::withoutTenancy()->where('user_id', $login->id)->sole();

    $this->actingAs(Actors::companyOwner($company))
        ->postJson(route('admin.driver.hire-status', $driver->id), terminatePayload())->assertOk();

    $this->actingAs($login->fresh())->get(route('dashboard'))->assertRedirect();
    $this->assertGuest();
});

it('keeps the DQ file and shows the driver under Terminated', function () {
    $owner = Actors::companyOwner();
    $driver = DriverFactory::new()->forCompany(Actors::companyOf($owner))->create();

    $this->actingAs($owner)->postJson(route('admin.driver.hire-status', $driver->id), terminatePayload())->assertOk();

    expect(Driver::withoutTenancy()->find($driver->id))->not->toBeNull();
    $this->actingAs($owner)->get(route('admin.driver.show', $driver->id))->assertOk()
        ->assertSee('Terminated')->assertDontSee('data-dialog-open="terminate-dialog"', false);
    $this->actingAs($owner)->get(route('admin.driver.index'))->assertOk()->assertSee('data-value="terminated"', false);
});

it('refuses a driver who is not active, a future date and a date before the hire', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);
    $pending = DriverFactory::new()->forCompany($company)->status('pending')->create();
    $active = DriverFactory::new()->forCompany($company)->create(['hire_date' => now()->subMonth()]);

    $this->actingAs($owner)->postJson(route('admin.driver.hire-status', $pending->id), terminatePayload())->assertStatus(422);
    $this->actingAs($owner)->postJson(route('admin.driver.hire-status', $active->id), terminatePayload(['termination_date' => now()->addDay()->toDateString()]))->assertStatus(422);
    $this->actingAs($owner)->postJson(route('admin.driver.hire-status', $active->id), terminatePayload(['termination_date' => now()->subYear()->toDateString()]))->assertStatus(422);

    expect($active->fresh()->employment_status)->toBe('active');
});

it('needs drivers.hire', function () {
    $company = Actors::company();
    $driver = DriverFactory::new()->forCompany($company)->create();
    $viewer = Actors::memberWithPermissions($company, ['drivers.view', 'drivers.edit']);

    $this->actingAs($viewer)->postJson(route('admin.driver.hire-status', $driver->id), terminatePayload())->assertForbidden();
    $this->actingAs($viewer)->get(route('admin.driver.show', $driver->id))->assertDontSee('data-dialog-open="terminate-dialog"', false);

    expect($driver->fresh()->employment_status)->toBe('active');
});

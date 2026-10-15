<?php

use App\Models\Driver;
use Database\Factories\DriverFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\Route;
use Tests\Support\Actors;

/*
| CMP-03: destroy() hard-deleted the company and its owner user, and the FK cascades removed
| every driver, vehicle, subscription and payment row while their files stayed on disk.
| Decided PO-13 (2026-10-11): a company is only ever deactivated. That is a platform action
| now (platform.companies.status, WP8a), and companies.status blocks every user of the company.
*/

function deactivate($test, $company): void
{
    $test->actingAs(Actors::platformOwner(), 'platform')
        ->post(route('platform.companies.status', $company), ['status' => 'deactivated', 'reason' => 'Requested by the owner'])
        ->assertSessionHasNoErrors();
}

it('does not let a company owner deactivate a company', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);

    $this->actingAs($owner)
        ->postJson(route('platform.companies.status', $company->id), ['status' => 'deactivated', 'reason' => 'x'])
        ->assertUnauthorized();

    expect($company->fresh()->status)->toBe('active');
});

it('has no tenant-side company delete route', function () {
    expect(Route::has('admin.settings.company.destroy'))->toBeFalse();
});

it('deactivates the company and keeps every record (decided PO-13, 2026-10-11)', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);
    $driver = DriverFactory::new()->forCompany($company)->create();
    $vehicle = VehicleFactory::new()->forCompany($company)->create();

    deactivate($this, $company);

    expect($company->fresh()->status)->toBe('deactivated')
        ->and(Driver::withoutTenancy()->find($driver->id))->not->toBeNull()
        ->and($vehicle->fresh())->not->toBeNull()
        ->and($company->subscriptions()->count())->toBe(1);
});

it('locks the deactivated company\'s owner out', function () {
    $owner = Actors::companyOwner();
    $owner->update(['password' => bcrypt('secret-password')]);

    deactivate($this, Actors::companyOf($owner));
    Auth::guard('platform')->logout();
    Auth::shouldUse('web');

    $this->post(route('login'), ['email' => $owner->email, 'password' => 'secret-password']);
    $this->get(route('dashboard'))->assertRedirect();

    expect(Auth::guard('web')->check())->toBeFalse();
});

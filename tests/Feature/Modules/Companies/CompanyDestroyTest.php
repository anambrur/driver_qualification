<?php

use App\Models\Driver;
use App\Models\User;
use Database\Factories\CompanyFactory;
use Database\Factories\DriverFactory;
use Database\Factories\VehicleFactory;
use Tests\Support\Actors;

/*
| CMP-03: destroy() hard-deleted the company and its owner user, and the FK cascades removed
| every driver, vehicle, subscription and payment row while their files stayed on disk.
| Decided PO-13 (2026-10-11): destroy() only deactivates the company and its owner, and a
| super-admin can't deactivate their own company.
*/

it('does not let a company owner delete a company', function () {
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);

    $this->actingAs($owner)
        ->deleteJson(route('admin.settings.company.destroy', $company->id))
        ->assertForbidden();

    expect($company->fresh())->not->toBeNull();
});

it('does not let a plain user with companies.view delete a company', function () {
    $user = Actors::plainUser(['companies.view']);
    $company = CompanyFactory::new()->create();

    $this->actingAs($user)
        ->deleteJson(route('admin.settings.company.destroy', $company->id))
        ->assertForbidden();

    expect($company->fresh())->not->toBeNull();
});

it('deactivates the company and its owner and keeps every record (decided PO-13, 2026-10-11)', function () {
    $admin = Actors::superAdmin();
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);
    $driver = DriverFactory::new()->create(['company_id' => $company->id]);
    $vehicle = VehicleFactory::new()->forCompany($company)->create();

    $this->actingAs($admin)
        ->deleteJson(route('admin.settings.company.destroy', $company->id))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($company->fresh()->status)->toBe('inactive')
        ->and($owner->fresh()->status)->toBe('inactive')
        ->and(Driver::withoutTenancy()->find($driver->id))->not->toBeNull()
        ->and($vehicle->fresh())->not->toBeNull()
        ->and($owner->fresh()->subscriptions()->count())->toBe(1);
});

it('locks the deactivated owner out', function () {
    $owner = Actors::companyOwner(['password' => bcrypt('secret-password')]);

    $this->actingAs(Actors::superAdmin())
        ->deleteJson(route('admin.settings.company.destroy', Actors::companyOf($owner)->id))
        ->assertOk();
    auth()->logout();

    $this->post(route('login'), ['email' => $owner->email, 'password' => 'secret-password']);

    $this->assertGuest();
});

it('refuses to deactivate the super-admin\'s own company (decided PO-13)', function () {
    $admin = Actors::superAdmin();
    $company = Actors::companyOf($admin);

    $this->actingAs($admin)
        ->deleteJson(route('admin.settings.company.destroy', $company->id))
        ->assertStatus(422);

    expect($company->fresh()->status)->toBe('active')
        ->and($admin->fresh()->status)->toBe('active');
});

it('offers Deactivate, not Delete, and not for the admin\'s own company', function () {
    $admin = Actors::superAdmin();
    $other = Actors::companyOf(Actors::companyOwner());

    $rows = collect($this->actingAs($admin)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get(route('admin.settings.company', ['draw' => 1]))
        ->assertOk()->json('data'))->keyBy('id');

    expect($rows[$other->id]['action'])->toContain('deleteCompany('.$other->id.')')->toContain('title="Deactivate"')
        ->and($rows[Actors::companyOf($admin)->id]['action'])->not->toContain('deleteCompany(');
});

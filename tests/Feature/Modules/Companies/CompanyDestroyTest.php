<?php

use App\Models\Driver;
use App\Models\User;
use Database\Factories\CompanyFactory;
use Database\Factories\DriverFactory;
use Tests\Support\Actors;

/*
| CMP-03 (DEFERRED): destroy() hard-deletes the company and its owner user, and the FK cascades
| remove every driver, vehicle, subscription and payment row while their files stay on disk.
| Whether that is intended (vs. soft delete / deactivate) is a product decision, so these tests
| only guard who can delete and document the current cascade.
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

it('currently deletes the owner and cascades to the company drivers', function () {
    $admin = Actors::superAdmin();
    $owner = Actors::companyOwner();
    $company = Actors::companyOf($owner);
    $driver = DriverFactory::new()->create(['company_id' => $company->id]);

    $this->actingAs($admin)
        ->deleteJson(route('admin.settings.company.destroy', $company->id))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(User::find($owner->id))->toBeNull()
        ->and(Driver::withTrashed()->find($driver->id))->toBeNull();
});

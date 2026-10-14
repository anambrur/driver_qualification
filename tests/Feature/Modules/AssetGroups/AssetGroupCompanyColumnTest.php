<?php

use App\Models\AssetGroup;
use App\Tenancy\TenantContext;
use Database\Factories\AssetGroupFactory;
use Database\Factories\CompanyFactory;
use Database\Factories\DriverFactory;
use Database\Factories\VehicleFactory;
use Tests\Feature\Modules\AssetGroups\AssetGroupActors;
use Tests\Support\Actors;

/*
| Follow-up: asset groups carry their own company_id. Tenant scoping and name uniqueness use it
| directly.
*/

describe('company_id', function () {
    it('stores the tenant\'s company on create', function () {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);

        $this->actingAs($tenant)->postJson(route('admin.asset-group.store'), [
            'group_name' => 'GR#1',
            'driver_id' => DriverFactory::new()->forCompany($company)->create()->id,
            'vehicle_id' => VehicleFactory::new()->forCompany($company)->create()->id,
            'status' => 'active',
            'company_id' => CompanyFactory::new()->create()->id,
        ])->assertOk();

        expect(AssetGroup::withoutTenancy()->sole()->company_id)->toBe($company->id);
    });

    it('stores the vehicle\'s company when a super-admin creates a group', function () {
        $company = CompanyFactory::new()->create();

        $this->actingAs(Actors::superAdmin())->postJson(route('admin.asset-group.store'), [
            'group_name' => 'GR#1',
            'driver_id' => DriverFactory::new()->forCompany($company)->create()->id,
            'vehicle_id' => VehicleFactory::new()->forCompany($company)->create()->id,
            'status' => 'active',
        ])->assertOk();

        expect(AssetGroup::withoutTenancy()->sole()->company_id)->toBe($company->id);
    });

    it('moves the group when a super-admin assigns another company\'s vehicle and driver', function () {
        $group = AssetGroupFactory::new()->create();
        $target = CompanyFactory::new()->create();

        $this->actingAs(Actors::superAdmin())->putJson(route('admin.asset-group.update', $group->id), [
            'group_name' => $group->group_name,
            'driver_id' => DriverFactory::new()->forCompany($target)->create()->id,
            'vehicle_id' => VehicleFactory::new()->forCompany($target)->create()->id,
            'status' => 'active',
        ])->assertOk();

        expect($group->fresh()->company_id)->toBe($target->id);
    });

    it('scopes tenants by the group\'s own company_id', function () {
        $tenant = AssetGroupActors::tenant();
        $own = AssetGroupFactory::new()->create(['company_id' => AssetGroupActors::company($tenant)->id]);
        AssetGroupFactory::new()->create();

        $this->actingAs($tenant)
            ->getJson(route('admin.asset-group.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.id', $own->id);
    });
});

it('fills company_id from the tenant context when code creates a group without one', function () {
    $vehicle = VehicleFactory::new()->create();

    $driverId = DriverFactory::new()->forCompany($vehicle->company)->create()->id;
    $group = app(TenantContext::class)->run($vehicle->company_id, fn () => AssetGroup::create([
        'group_name' => 'Seeded',
        'driver_id' => $driverId,
        'vehicle_id' => $vehicle->id,
        'status' => 'active',
    ]));

    expect($group->fresh()->company_id)->toBe($vehicle->company_id);
});

<?php

use App\Models\AssetGroup;
use Database\Factories\AssetGroupFactory;
use Database\Factories\DriverFactory;
use Database\Factories\VehicleFactory;
use Tests\Feature\Modules\AssetGroups\AssetGroupActors;

/*
| Follow-up: the form's driver <select> was named primary_driver_name with driver ids as values,
| so the group stored "42" as the driver's name. The select now posts driver_id, and the group
| shows the driver record's name (the stored name column was dropped in WP1).
*/

describe('primary driver', function () {
    it('stores the selected driver\'s name, not whatever the client sends', function () {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        $driver = DriverFactory::new()->forCompany($company)->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $this->actingAs($tenant)->postJson(route('admin.asset-group.store'), [
            'group_name' => 'GR#1',
            'driver_id' => $driver->id,
            'primary_driver_name' => (string) $driver->id,
            'vehicle_id' => VehicleFactory::new()->forCompany($company)->create()->id,
            'status' => 'active',
        ])->assertOk();

        expect(AssetGroup::withoutTenancy()->sole()->driver_id)->toBe($driver->id);
        $this->actingAs($tenant)->getJson(route('admin.asset-group.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('data.0.drivers_info', fn (string $html) => str_contains($html, 'Ada Lovelace') && ! str_contains($html, '>'.$driver->id.'<'));
    });

    it('updates the name when another driver is picked', function () {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        $group = AssetGroupFactory::new()->forCompany($company)->create();
        $newDriver = DriverFactory::new()->forCompany($company)->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);

        $this->actingAs($tenant)->putJson(route('admin.asset-group.update', $group->id), [
            'group_name' => $group->group_name,
            'driver_id' => $newDriver->id,
            'vehicle_id' => $group->vehicle_id,
            'status' => 'active',
        ])->assertOk();

        expect(AssetGroup::withoutTenancy()->find($group->id)->driver_id)->toBe($newDriver->id);
        $this->actingAs($tenant)->getJson(route('admin.asset-group.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('data.0.drivers_info', fn (string $html) => str_contains($html, 'Grace Hopper'));
    });

    it('requires a driver on update', function () {
        $tenant = AssetGroupActors::tenant();
        $group = AssetGroupFactory::new()->forCompany(AssetGroupActors::company($tenant))->create();

        $this->actingAs($tenant)->putJson(route('admin.asset-group.update', $group->id), [
            'group_name' => $group->group_name,
            'vehicle_id' => $group->vehicle_id,
            'status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors('driver_id');
    });

    it('returns only the driver\'s id and name from edit, so an inactive driver can be shown', function () {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        $driver = DriverFactory::new()->forCompany($company)->status('inactive')->create();
        $group = AssetGroupFactory::new()->forCompany($company)->create(['driver_id' => $driver->id]);

        $data = $this->actingAs($tenant)->getJson(route('admin.asset-group.edit', $group->id))->assertOk()->json('data.driver');

        expect($data)->toBe([
            'id' => $driver->id,
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'employment_status' => 'inactive',
        ]);
    });

    it('names the driver select driver_id in the form', function () {
        $this->actingAs(AssetGroupActors::tenant())
            ->get(route('admin.asset-group.index'))
            ->assertOk()
            ->assertSee('name="driver_id"', false)
            ->assertSee('id="f-driver_id"', false)
            ->assertDontSee('name="primary_driver_name"', false);
    });
});

<?php

use App\Models\AssetGroup;
use Database\Factories\AssetGroupFactory;
use Database\Factories\DriverFactory;
use Database\Factories\VehicleFactory;
use Tests\Feature\Modules\AssetGroups\AssetGroupActors;

/*
| Follow-up: the form's driver <select> was named primary_driver_name with driver ids as values,
| so the group stored "42" as the driver's name. The select now posts driver_id and the server
| takes the name from the driver record. Migration 2026_10_09_000500 repairs existing rows.
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

        expect(AssetGroup::sole()->primary_driver_name)->toBe('Ada Lovelace');
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

        expect($group->fresh())
            ->driver_id->toBe($newDriver->id)
            ->primary_driver_name->toBe('Grace Hopper');
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
            'status' => 'inactive',
        ]);
    });

    it('names the driver select driver_id in the form', function () {
        $this->actingAs(AssetGroupActors::tenant())
            ->get(route('admin.asset-group.index'))
            ->assertOk()
            ->assertSee('<select name="driver_id" id="driver_id"', false)
            ->assertDontSee('name="primary_driver_name"', false)
            ->assertSee('id="driver_id_error"', false);
    });

    it('repairs groups whose primary_driver_name holds the driver id', function () {
        $broken = AssetGroupFactory::new()->create();
        $broken->driver->update(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $broken->update(['primary_driver_name' => (string) $broken->driver_id]);
        $named = AssetGroupFactory::new()->create(['primary_driver_name' => 'Typed by hand']);
        $otherNumber = AssetGroupFactory::new()->create(['primary_driver_name' => '999999']);

        (require database_path('migrations/2026_10_09_000500_fix_asset_group_primary_driver_names.php'))->up();

        expect($broken->fresh()->primary_driver_name)->toBe('Ada Lovelace')
            ->and($named->fresh()->primary_driver_name)->toBe('Typed by hand')
            ->and($otherNumber->fresh()->primary_driver_name)->toBe('999999');
    });
});

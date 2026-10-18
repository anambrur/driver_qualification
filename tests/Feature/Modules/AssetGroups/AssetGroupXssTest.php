<?php

use Database\Factories\AssetGroupFactory;
use Database\Factories\DriverFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Tests\Feature\Modules\AssetGroups\AssetGroupActors;

/*
| AGR-04: group_info / drivers_info / assets_info / action are raw DataTables columns. The group
| name, driver names/phones and vehicle/trailer unit numbers/VINs were concatenated unescaped,
| and delete/restore used onclick="fn(id, '<addslashes(name)>')".
*/

// The second value fits vehicles.vin / trailers.vin (17 characters with the prefix)
dataset('agr xss payloads', [
    'script tag' => ['<script>alert(1)</script>', '<script>'],
    'attribute break-out' => ['x" onmouseover="alert(1)', 'x" onclick="'],
    'entity-encoded quote' => ['&#39;);alert(1);//', '&#39;);//'],
]);

describe('AGR-04', function () {
    it('escapes user text in every raw column', function (string $payload, string $vin) {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        $vehicle = VehicleFactory::new()->forCompany($company)->create(['unit_no' => 'V'.$payload, 'vin' => 'VIN'.$vin]);
        $trailer = TrailerFactory::new()->forCompany($company)->create(['unit_no' => 'T'.$payload, 'vin' => 'TVIN'.$vin]);
        AssetGroupFactory::new()->create([
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'group_name' => 'G'.$payload,
            'driver_id' => DriverFactory::new()->forCompany($company)->create(['first_name' => 'P'.$payload, 'main_phone' => 'PP'.$payload])->id,
            'co_driver_id' => DriverFactory::new()->forCompany($company)->create(['first_name' => 'S'.$payload, 'main_phone' => 'SP'.$payload])->id,
        ]);

        $row = $this->actingAs($tenant)
            ->getJson(route('admin.asset-group.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('data.0');

        foreach (['group_info', 'drivers_info', 'assets_info', 'action'] as $column) {
            expect($row[$column])->not->toContain($payload)->not->toContain('VIN'.$vin);
        }

        expect($row['group_info'])->toContain(e('G'.$payload))
            ->and($row['drivers_info'])->toContain(e('P'.$payload))->toContain(e('SP'.$payload))
            ->and($row['assets_info'])->toContain(e('V'.$payload))->toContain(e('TVIN'.$vin));
    })->with('agr xss payloads');

    // The Restore button (Deleted filter) is covered in AssetGroupListFiltersTest.
    it('puts the group name in data attributes instead of inline onclick', function () {
        $name = "x' onmouseover=\"alert(1)";
        $tenant = AssetGroupActors::tenant();
        $group = AssetGroupFactory::new()->forCompany(AssetGroupActors::company($tenant))->create(['group_name' => $name]);

        $action = $this->actingAs($tenant)
            ->getJson(route('admin.asset-group.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('data.0.action');

        expect($action)
            ->not->toContain('onclick="deleteAssetGroup')
            ->toContain('data-action="delete"')
            ->toContain('data-id="'.$group->id.'"')
            ->toContain('data-name="'.e($name).'"');
    });

    it('escapes vehicle and trailer details before inserting them into the form', function () {
        $this->actingAs(AssetGroupActors::tenant())
            ->get(route('admin.asset-group.index'))
            ->assertOk()
            ->assertDontSee('${vehicle.unit_no || vehicle.id}', false)
            ->assertDontSee('${trailer.unit_no || trailer.id}', false)
            // Unit details are written with textContent; row actions go through dqCrud.page()
            ->assertSee('detail.textContent = unit ?', false)
            ->assertSee('window.dqCrud.page(', false);
    });
});

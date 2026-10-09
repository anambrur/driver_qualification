<?php

use Database\Factories\AssetGroupFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Tests\Feature\Modules\AssetGroups\AssetGroupActors;
use Tests\Support\Actors;

/*
| AGR-04: group_info / drivers_info / assets_info / action are raw DataTables columns. The group
| name, driver names/phones and vehicle/trailer unit numbers/VINs were concatenated unescaped,
| and delete/restore used onclick="fn(id, '<addslashes(name)>')".
*/

dataset('agr xss payloads', [
    'script tag' => ['<script>alert(1)</script>'],
    'attribute break-out' => ['x" onmouseover="alert(1)'],
    'entity-encoded quote' => ['&#39;);alert(1);//'],
]);

describe('AGR-04', function () {
    it('escapes user text in every raw column', function (string $payload) {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        $vehicle = VehicleFactory::new()->forCompany($company)->create(['unit_no' => 'V'.$payload, 'vin' => 'VIN'.$payload]);
        $trailer = TrailerFactory::new()->forCompany($company)->create(['unit_no' => 'T'.$payload, 'vin' => 'TVIN'.$payload]);
        AssetGroupFactory::new()->create([
            'vehicle_id' => $vehicle->id,
            'trailer_id' => $trailer->id,
            'group_name' => 'G'.$payload,
            'primary_driver_name' => 'P'.$payload,
            'primary_driver_phone' => 'PP'.$payload,
            'second_driver_name' => 'S'.$payload,
            'second_driver_phone' => 'SP'.$payload,
        ]);

        $row = $this->actingAs($tenant)
            ->getJson(route('admin.asset-group.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('data.0');

        foreach (['group_info', 'drivers_info', 'assets_info', 'action'] as $column) {
            expect($row[$column])->not->toContain($payload);
        }

        expect($row['group_info'])->toContain(e('G'.$payload))
            ->and($row['drivers_info'])->toContain(e('P'.$payload))->toContain(e('SP'.$payload))
            ->and($row['assets_info'])->toContain(e('V'.$payload))->toContain(e('TVIN'.$payload));
    })->with('agr xss payloads');

    // The Restore button (Deleted filter) is covered in AssetGroupListFiltersTest.
    it('puts the group name in data attributes instead of inline onclick', function () {
        $name = "x' onmouseover=\"alert(1)";
        $group = AssetGroupFactory::new()->create(['group_name' => $name]);

        $action = $this->actingAs(Actors::superAdmin())
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
        $this->actingAs(Actors::superAdmin())
            ->get(route('admin.asset-group.index'))
            ->assertOk()
            ->assertDontSee('${vehicle.unit_no || vehicle.id}', false)
            ->assertDontSee('${trailer.unit_no || trailer.id}', false)
            ->assertSee('[data-action="delete"]', false)
            ->assertSee('[data-action="restore"]', false);
    });
});

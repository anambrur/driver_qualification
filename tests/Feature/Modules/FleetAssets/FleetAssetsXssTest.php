<?php

use App\Models\EquipmentType;
use App\Models\FuelType;
use App\Models\VehicleGroup;
use App\Models\VehicleType;
use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| FLT-01: vehicle_info / trailer_info, type_group / equipment_group, fuel_odometer and action
| are raw DataTables columns. unit_no, make, model, VIN and the lookup names were concatenated
| unescaped, and Delete/Restore used onclick="fn(id, '<addslashes(unit_no)>')".
*/

dataset('flt xss payloads', [
    'script tag' => ['<script>alert(1)</script>'],
    'attribute break-out' => ['x" onmouseover="alert(1)'],
    'entity-encoded quote' => ['&#39;);alert(1);//'],
]);

describe('FLT-01: the vehicle and trailer lists escape tenant-controlled data', function () {
    it('escapes vehicle fields and lookup names in every raw column', function (string $payload) {
        [$tenant, $vehicle] = FleetAssets::tenantWithAsset('vehicle', [
            'unit_no' => 'U'.$payload,
            'make' => 'MK'.$payload,
            'model' => 'MD'.$payload,
            'vin' => 'V'.$payload,
            'vehicle_type_id' => VehicleType::create(['name' => 'T'.$payload])->id,
            'vehicle_group_id' => VehicleGroup::create(['name' => 'G'.$payload])->id,
            'fuel_type_id' => FuelType::create(['name' => 'F'.$payload])->id,
        ]);

        $row = $this->actingAs($tenant)
            ->getJson(FleetAssets::route('vehicle', 'index'), FleetAssets::AJAX)
            ->assertOk()
            ->json('data.0');

        foreach (['vehicle_info', 'type_group', 'fuel_odometer', 'action'] as $column) {
            expect($row[$column])->not->toContain($payload);
        }

        expect($row['vehicle_info'])->toContain(e('U'.$payload))->toContain(e('MK'.$payload))
            ->toContain(e('MD'.$payload))->toContain(e('V'.$payload))
            ->and($row['type_group'])->toContain(e('T'.$payload))->toContain(e('G'.$payload))
            ->and($row['fuel_odometer'])->toContain(e('F'.$payload));
    })->with('flt xss payloads');

    it('escapes trailer fields and lookup names in every raw column', function (string $payload) {
        [$tenant] = FleetAssets::tenantWithAsset('trailer', [
            'unit_no' => 'U'.$payload,
            'make' => 'MK'.$payload,
            'model' => 'MD'.$payload,
            'vin' => 'V'.$payload,
            'equipment_types_id' => EquipmentType::create(['name' => 'E'.$payload])->id,
            'vehicle_group_id' => VehicleGroup::create(['name' => 'G'.$payload])->id,
        ]);

        $row = $this->actingAs($tenant)
            ->getJson(FleetAssets::route('trailer', 'index'), FleetAssets::AJAX)
            ->assertOk()
            ->json('data.0');

        foreach (['trailer_info', 'equipment_group', 'action'] as $column) {
            expect($row[$column])->not->toContain($payload);
        }

        expect($row['trailer_info'])->toContain(e('U'.$payload))->toContain(e('MK'.$payload))
            ->toContain(e('MD'.$payload))->toContain(e('V'.$payload))
            ->and($row['equipment_group'])->toContain(e('E'.$payload))->toContain(e('G'.$payload));
    })->with('flt xss payloads');

    it('shows a super-admin another tenant\'s payload escaped too', function (string $assetType) {
        $payload = '<img src=x onerror=alert(1)>';
        FleetAssets::tenantWithAsset($assetType, ['unit_no' => $payload]);

        $row = $this->actingAs(Actors::superAdmin())
            ->getJson(FleetAssets::route($assetType, 'index'), FleetAssets::AJAX)
            ->assertOk()
            ->json('data.0');

        expect($row[$assetType.'_info'])->not->toContain($payload)->toContain(e($payload))
            ->and($row['action'])->not->toContain($payload);
    })->with(FleetAssets::TYPES);

    it('puts the unit number in data attributes instead of inline onclick', function (string $assetType) {
        $unitNo = "x' onmouseover=\"alert(1)";
        [$tenant, $asset] = FleetAssets::tenantWithAsset($assetType, ['unit_no' => $unitNo]);
        $fn = ucfirst($assetType);

        $action = $this->actingAs($tenant)
            ->getJson(FleetAssets::route($assetType, 'index'), FleetAssets::AJAX)
            ->assertOk()
            ->json('data.0.action');

        expect($action)
            ->not->toContain('onclick="delete'.$fn)
            ->not->toContain('onclick="edit'.$fn)
            ->toContain('data-action="edit"')
            ->toContain('data-action="delete"')
            ->toContain('data-id="'.$asset->id.'"')
            ->toContain('data-name="'.e($unitNo).'"');
    })->with(FleetAssets::TYPES);

    it('wires the data-action buttons to one listener per action on the page', function (string $assetType) {
        $this->actingAs(Actors::companyOwner())
            ->get(FleetAssets::route($assetType, 'index'))
            ->assertOk()
            ->assertSee('[data-action="edit"]', false)
            ->assertSee('[data-action="delete"]', false)
            ->assertSee('[data-action="restore"]', false);
    })->with(FleetAssets::TYPES);
});

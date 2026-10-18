<?php

use App\Models\EquipmentType;
use App\Models\FuelType;
use App\Models\VehicleGroup;
use App\Models\VehicleType;
use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| FLT-01: the vehicle and trailer lists concatenated unit no, make, model, VIN and the
| type/group/fuel/equipment names into raw DataTables columns. The Delete/Restore buttons put
| the unit no into an inline onclick="deleteVehicle(id, '...')" with addslashes(), which does
| not stop HTML entities (&#39;) or a double quote from breaking out of the attribute. Any tenant
| user can set these fields, and a super-admin's list shows every tenant's rows.
*/

const FLT_PAYLOAD = '<img src=x onerror=alert(1)>';
// Fits vehicles.vin / trailers.vin (17 characters)
const FLT_VIN_PAYLOAD = '<img src=x>v';
const FLT_QUOTE_PAYLOAD = 'x&#39;);alert(1);//" onmouseover="alert(2)';

function fltFields(): array
{
    return ['unit_no' => FLT_PAYLOAD, 'make' => FLT_PAYLOAD.'m', 'model' => FLT_PAYLOAD.'o', 'vin' => FLT_VIN_PAYLOAD];
}

function fltRow($test, $user, string $route): array
{
    return $test->actingAs($user)->withHeaders(FleetAssets::ajax())
        ->get(route($route, ['draw' => 1]))
        ->assertOk()->json('data.0');
}

describe('FLT-01: the lists escape tenant-controlled data', function () {
    it('escapes vehicle fields and type, group and fuel names in the vehicle list', function () {
        $owner = Actors::companyOwner();
        FleetAssets::vehicleOf($owner, fltFields() + [
            'vehicle_type_id' => VehicleType::create(['name' => FLT_PAYLOAD.'t'])->id,
            'vehicle_group_id' => VehicleGroup::create(['name' => FLT_PAYLOAD.'g'])->id,
            'fuel_type_id' => FuelType::create(['name' => FLT_PAYLOAD.'f'])->id,
        ]);

        $row = fltRow($this, $owner, 'admin.vehicle.index');

        expect($row['vehicle_info'].$row['type_group'].$row['fuel_odometer'].$row['action'])->not->toContain('<img')
            ->and($row['vehicle_info'])->toContain(e(FLT_PAYLOAD))
            ->and($row['vehicle_info'])->toContain(e(FLT_PAYLOAD.'m'))
            ->and($row['vehicle_info'])->toContain(e(FLT_PAYLOAD.'o'))
            ->and($row['vehicle_info'])->toContain(e(FLT_VIN_PAYLOAD))
            ->and($row['type_group'])->toContain(e(FLT_PAYLOAD.'t'))
            ->and($row['type_group'])->toContain(e(FLT_PAYLOAD.'g'))
            ->and($row['fuel_odometer'])->toContain(e(FLT_PAYLOAD.'f'));
    });

    it('escapes trailer fields and equipment and group names in the trailer list', function () {
        $owner = Actors::companyOwner();
        FleetAssets::trailerOf($owner, fltFields() + [
            'equipment_type_id' => EquipmentType::create(['name' => FLT_PAYLOAD.'e'])->id,
            'vehicle_group_id' => VehicleGroup::create(['name' => FLT_PAYLOAD.'g'])->id,
        ]);

        $row = fltRow($this, $owner, 'admin.trailer.index');

        expect($row['trailer_info'].$row['equipment_group'].$row['action'])->not->toContain('<img')
            ->and($row['trailer_info'])->toContain(e(FLT_PAYLOAD))
            ->and($row['trailer_info'])->toContain(e(FLT_PAYLOAD.'m'))
            ->and($row['trailer_info'])->toContain(e(FLT_PAYLOAD.'o'))
            ->and($row['trailer_info'])->toContain(e(FLT_VIN_PAYLOAD))
            ->and($row['equipment_group'])->toContain(e(FLT_PAYLOAD.'e'))
            ->and($row['equipment_group'])->toContain(e(FLT_PAYLOAD.'g'));
    });

    it('escapes the owner column', function () {
        $owner = Actors::companyOwner();
        FleetAssets::trailerOf($owner, ['owned_by' => FLT_PAYLOAD.'b']);

        // The list is one company's, so it has no company column (UI-06)
        $trailer = fltRow($this, $owner, 'admin.trailer.index');

        expect($trailer)->not->toHaveKey('company_name')
            ->and($trailer['owned_by'])->not->toContain('<img');
    });
});

describe('FLT-01: the action buttons carry no inline JavaScript', function () {
    it('puts the unit no in an escaped data attribute, not in onclick', function (string $route, bool $deleted) {
        $owner = Actors::companyOwner();
        $asset = $route === 'admin.vehicle.index'
            ? FleetAssets::vehicleOf($owner, ['unit_no' => FLT_QUOTE_PAYLOAD])
            : FleetAssets::trailerOf($owner, ['unit_no' => FLT_QUOTE_PAYLOAD]);
        if ($deleted) {
            $asset->delete();
        }

        $action = $this->actingAs($owner)->withHeaders(FleetAssets::ajax())
            ->get(route($route, ['draw' => 1, 'status' => $deleted ? 'deleted' : '']))
            ->assertOk()->json('data.0.action');

        expect($action)->not->toContain('onclick')
            ->and($action)->not->toContain('onmouseover="')
            ->and($action)->toContain('data-id="'.$asset->id.'"')
            ->and($action)->toContain('data-name="'.e(FLT_QUOTE_PAYLOAD).'"');
    })->with([
        'vehicle' => 'admin.vehicle.index',
        'trailer' => 'admin.trailer.index',
    ])->with(['active row' => false, 'deleted row' => true]);

    it('wires the buttons through the shared delegated handler, without inline handlers', function (string $route) {
        $html = $this->actingAs(Actors::companyOwner())->get(route($route))->assertOk()->getContent();

        // dqCrud.page() listens for [data-action] clicks; window.notify and the field errors
        // write text, never HTML (resources/js/crud.js, app.js)
        expect($html)->toContain('window.dqCrud.page(')
            ->and($html)->not->toMatch('/\son(click|mouseover)="(?!this\.closest|if \(event\.target)/');
    })->with(['vehicles' => 'admin.vehicle.index', 'trailers' => 'admin.trailer.index']);
});

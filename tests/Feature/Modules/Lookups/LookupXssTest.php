<?php

use App\Models\EquipmentType;
use App\Models\FuelType;
use App\Models\MaintenanceCategory;
use App\Models\VehicleGroup;
use App\Models\VehicleType;
use Tests\Support\Actors;

/*
| LKP-02: the DataTables `action` column is raw HTML. Lookup names used to be dropped into
| onclick="deleteX(id, '<addslashes(name)>')", which does not stop a `"` from closing the
| attribute or an HTML entity like &#39; from closing the JS string.
*/

dataset('named lookups', [
    'vehicle type' => [VehicleType::class, 'admin.vehicle.type'],
    'vehicle group' => [VehicleGroup::class, 'admin.vehicle.group'],
    'fuel type' => [FuelType::class, 'admin.fuel.type'],
    'equipment type' => [EquipmentType::class, 'admin.equipment.type'],
    'maintenance category' => [MaintenanceCategory::class, 'admin.maintenance.category'],
]);

dataset('xss names', [
    'attribute break-out' => ['x" onmouseover="alert(1)'],
    'entity-encoded quote' => ['&#39;);alert(1);//'],
]);

it('renders lookup names in the action column as escaped data attributes', function (string $model, string $route, string $name) {
    $model::query()->create(['name' => $name]);

    $action = $this->actingAs(Actors::superAdmin())
        ->getJson(route("$route.index"), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->json('data.0.action');

    expect($action)
        ->not->toContain($name)
        ->not->toContain('onclick="delete')
        ->toContain('data-name="'.e($name).'"');
})->with('named lookups')->with('xss names');

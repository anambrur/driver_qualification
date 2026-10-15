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
    'vehicle type' => [VehicleType::class, 'platform.lookups.vehicle-types'],
    'vehicle group' => [VehicleGroup::class, 'platform.lookups.vehicle-groups'],
    'fuel type' => [FuelType::class, 'platform.lookups.fuel-types'],
    'equipment type' => [EquipmentType::class, 'platform.lookups.equipment-types'],
    'maintenance category' => [MaintenanceCategory::class, 'platform.lookups.maintenance-categories'],
]);

dataset('xss names', [
    'attribute break-out' => ['x" onmouseover="alert(1)'],
    'entity-encoded quote' => ['&#39;);alert(1);//'],
]);

it('renders lookup names in the action column as escaped data attributes', function (string $model, string $route, string $name) {
    $model::query()->create(['name' => $name]);

    $action = $this->actingAs(Actors::platformOwner(), 'platform')
        ->getJson(route("$route.index"), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->json('data.0.action');

    expect($action)
        ->not->toContain($name)
        ->not->toContain('onclick="delete')
        ->toContain('data-name="'.e($name).'"');
})->with('named lookups')->with('xss names');

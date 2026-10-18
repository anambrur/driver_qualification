<?php

use App\Models\DocumentType;
use App\Models\EquipmentType;
use App\Models\FuelType;
use App\Models\MaintenanceCategory;
use App\Models\VehicleGroup;
use App\Models\VehicleType;
use Tests\Support\Actors;

/*
| UI-06 (phase 2, batch 3a): the five name-only lookups share one page and one server-side table.
| Vehicle types used to answer edit/update with a page and a redirect, so the list's edit dialog
| never filled in.
*/

dataset('lookup pages', [
    'fuel types' => [FuelType::class, 'platform.lookups.fuel-types', 'Fuel types'],
    'vehicle types' => [VehicleType::class, 'platform.lookups.vehicle-types', 'Vehicle types'],
    'vehicle groups' => [VehicleGroup::class, 'platform.lookups.vehicle-groups', 'Vehicle groups'],
    'equipment types' => [EquipmentType::class, 'platform.lookups.equipment-types', 'Equipment types'],
    'maintenance categories' => [MaintenanceCategory::class, 'platform.lookups.maintenance-categories', 'Maintenance categories'],
]);

it('renders the shared lookup page', function (string $model, string $route, string $title) {
    $this->actingAs(Actors::platformOwner(), 'platform')->get(route("$route.index"))
        ->assertOk()
        ->assertSee("<h1 class=\"text-2xl font-semibold tracking-tight text-foreground\">$title</h1>", false)
        ->assertSee('id="lookup-dialog"', false)
        ->assertDontSee('cdn.datatables.net', false);
})->with('lookup pages');

it('pages the lookup server-side', function (string $model, string $route) {
    foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
        $model::query()->create(['name' => "Qz $name"]);
    }

    $json = $this->actingAs(Actors::platformOwner(), 'platform')
        ->getJson(route("$route.index", ['draw' => 1, 'start' => 1, 'length' => 1, 'search' => ['value' => 'Qz'], 'order' => [['column' => 1, 'dir' => 'desc']], 'columns' => [['data' => 'DT_RowIndex', 'name' => '', 'orderable' => 'false', 'searchable' => 'false'], ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true']]]), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->json();

    expect($json['recordsFiltered'])->toBe(3)
        ->and($json['data'])->toHaveCount(1)
        ->and($json['data'][0]['name'])->toBe('Qz Bravo');
})->with('lookup pages');

it('loads and saves a vehicle type as JSON for the edit dialog', function () {
    $type = VehicleType::query()->create(['name' => 'Tractor']);
    $this->actingAs(Actors::platformOwner(), 'platform');

    $this->getJson(route('platform.lookups.vehicle-types.edit', $type->id))
        ->assertOk()->assertJsonPath('data.name', 'Tractor');

    $this->putJson(route('platform.lookups.vehicle-types.update', $type->id), ['name' => 'Box truck'])
        ->assertOk()->assertJsonPath('success', true);

    expect($type->fresh()->name)->toBe('Box truck');
});

describe('document types', function () {
    it('filters by module and status on the server', function () {
        DocumentType::factory()->create(['name' => 'Qz MVR', 'module' => 'driver', 'status' => true]);
        DocumentType::factory()->create(['name' => 'Qz Old', 'module' => 'driver', 'status' => false]);
        DocumentType::factory()->create(['name' => 'Qz Registration', 'module' => 'vehicle', 'status' => true]);

        $names = fn (array $filters) => collect($this->actingAs(Actors::platformOwner(), 'platform')
            ->getJson(route('platform.lookups.document-types.index', ['draw' => 1, 'start' => 0, 'length' => 50, 'search' => ['value' => 'Qz'],
                'columns' => [['data' => 'name', 'name' => 'name', 'searchable' => 'true']]] + $filters), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->json('data'))->pluck('name')->sort()->values()->all();

        expect($names(['module' => 'driver']))->toBe(['Qz MVR', 'Qz Old'])
            ->and($names(['module' => 'driver', 'status' => 'active']))->toBe(['Qz MVR'])
            ->and($names(['status' => 'inactive']))->toBe(['Qz Old'])
            ->and($names(['module' => 'nope']))->toHaveCount(3);
    });

    it('gives tenants only the opt-out action', function () {
        DocumentType::factory()->create();

        $action = $this->actingAs(Actors::companyOwner())
            ->getJson(route('admin.settings.document-types.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->json('data.0.action');

        expect($action)->toContain('data-action="company-toggle"')
            ->not->toContain('data-action="delete"')
            ->not->toContain('data-action="toggle-status"');
    });
});

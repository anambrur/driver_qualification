<?php

use App\Models\AssetGroup;
use App\Tenancy\TenantContext;
use Database\Factories\AssetGroupFactory;
use Database\Factories\DriverFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Tests\Support\Actors;

/*
| WP5 group 4: asset groups carry the tenant scope (03 §4.2). Another company's group is a 404
| by URL, and another company's driver, vehicle or trailer is refused in the request body.
*/

beforeEach(function () {
    $this->companyA = Actors::company();
    $this->companyB = Actors::company();
    $this->groupA = AssetGroupFactory::new()->forCompany($this->companyA)->create();
    $this->groupB = AssetGroupFactory::new()->forCompany($this->companyB)->create();
    $this->ownerB = $this->companyB->owner;
});

it('shows each company only its own groups', function () {
    $ids = app(TenantContext::class)->run($this->companyB->id, fn () => AssetGroup::pluck('id')->all());

    expect($ids)->toBe([$this->groupB->id]);
});

it('returns 404 for another company\'s group by URL', function (string $method, string $route) {
    $this->groupA->delete();
    if ($route !== 'admin.asset-group.restore') {
        $this->groupA->restore();
    }

    $this->actingAs($this->ownerB)
        ->json($method, route($route, $this->groupA->id))
        ->assertNotFound();
})->with([
    'edit' => ['GET', 'admin.asset-group.edit'],
    'update' => ['PUT', 'admin.asset-group.update'],
    'destroy' => ['DELETE', 'admin.asset-group.destroy'],
    'restore' => ['POST', 'admin.asset-group.restore'],
]);

it('refuses another company\'s driver, vehicle or trailer in a new group', function (string $field) {
    $vehicleB = VehicleFactory::new()->forCompany($this->companyB)->create();
    $payload = [
        'group_name' => 'Mixed',
        'driver_id' => DriverFactory::new()->forCompany($this->companyB)->create()->id,
        'vehicle_id' => $vehicleB->id,
        'status' => 'active',
        $field => match ($field) {
            'driver_id' => $this->groupA->driver_id,
            'vehicle_id' => $this->groupA->vehicle_id,
            'trailer_id' => TrailerFactory::new()->forCompany($this->companyA)->create()->id,
        },
    ];

    $this->actingAs($this->ownerB)
        ->postJson(route('admin.asset-group.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field, 'errors');

    expect(AssetGroup::withoutTenancy()->count())->toBe(2);
})->with(['driver_id', 'vehicle_id', 'trailer_id']);

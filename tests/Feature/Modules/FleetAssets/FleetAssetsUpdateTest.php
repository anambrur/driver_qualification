<?php

use Database\Factories\AssetGroupFactory;
use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\ServiceLogFactory;
use Database\Factories\VehicleFactory;
use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| FLT-03: update() saved $request->all(). $fillable keeps out id, timestamps and deleted_at,
| and a tenant's company_id is overwritten with the asset's own, so a tenant cannot move or
| tamper with an asset. update() now saves only the validated fields.
|
| A super-admin can move an asset to another company. Asset groups, service logs and
| maintenance schedules carry their own company_id and stayed with the old company, which then
| kept a group/log/schedule pointing at another tenant's vehicle (and saw its unit no, VIN and
| odometer through them). Until the product owner decides whether a move should take that
| history along (see the report), a move is refused while such records exist.
*/

dataset('fleet assets', [
    'vehicles' => ['vehicle'],
    'trailers' => ['trailer'],
]);

describe('FLT-03: update only saves the form fields', function () {
    it('ignores company_id, id, timestamps and deleted_at posted by a tenant', function (string $kind) {
        $owner = Actors::companyOwner();
        $asset = FleetAssets::assetOf($owner, $kind);
        $createdAt = $asset->created_at->toDateTimeString();
        $other = Actors::companyOf(Actors::companyOwner());

        $this->actingAs($owner)
            ->putJson(route("admin.$kind.update", $asset->id), FleetAssets::formData($asset, [
                'company_id' => $other->id,
                'id' => 999999,
                'created_at' => '2000-01-01 00:00:00',
                'deleted_at' => now()->toDateTimeString(),
                'make' => 'Renamed',
            ]))
            ->assertOk();

        $fresh = FleetAssets::model($kind)::withTrashed()->findOrFail($asset->id);
        expect($fresh->company_id)->toBe($asset->company_id)
            ->and($fresh->created_at->toDateTimeString())->toBe($createdAt)
            ->and($fresh->deleted_at)->toBeNull()
            ->and($fresh->make)->toBe('Renamed');
    })->with('fleet assets');

    it('refuses another tenant\'s asset', function (string $kind) {
        $asset = FleetAssets::assetOf(Actors::companyOwner(), $kind);
        $intruder = Actors::companyOwner();

        $this->actingAs($intruder)
            ->putJson(route("admin.$kind.update", $asset->id), FleetAssets::formData($asset, ['make' => 'Hacked']))
            ->assertForbidden();
        $this->actingAs($intruder)->getJson(route("admin.$kind.edit", $asset->id))->assertForbidden();
        $this->actingAs($intruder)->deleteJson(route("admin.$kind.destroy", $asset->id))->assertForbidden();

        expect($asset->fresh()->make)->not->toBe('Hacked')->and($asset->fresh()->deleted_at)->toBeNull();
    })->with('fleet assets');

    it('saves the optional fields the form sends', function () {
        $owner = Actors::companyOwner();
        $vehicle = FleetAssets::vehicleOf($owner);

        $this->actingAs($owner)
            ->putJson(route('admin.vehicle.update', $vehicle->id), FleetAssets::vehicleFormData($vehicle, [
                'color' => 'White', 'no_axles' => 3, 'configuration' => 'cabover', 'notes' => 'Spare key in office', 'gvw' => '',
            ]))
            ->assertOk();

        expect($vehicle->fresh()->only(['color', 'no_axles', 'configuration', 'notes', 'gvw']))
            ->toBe(['color' => 'White', 'no_axles' => 3, 'configuration' => 'cabover', 'notes' => 'Spare key in office', 'gvw' => null]);
    });
});

describe('FLT-03: a super-admin moving an asset to another company', function () {
    it('refuses to move a vehicle that has company records', function (string $record) {
        $vehicle = VehicleFactory::new()->create();
        match ($record) {
            'asset group' => AssetGroupFactory::new()->create(['vehicle_id' => $vehicle->id]),
            'service log' => ServiceLogFactory::new()->forVehicle($vehicle)->create(),
            'maintenance schedule' => MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create(),
        };
        $target = Actors::companyOf(Actors::companyOwner());

        $this->actingAs(Actors::superAdmin())
            ->putJson(route('admin.vehicle.update', $vehicle->id), FleetAssets::vehicleFormData($vehicle, ['company_id' => $target->id]))
            ->assertStatus(422)->assertJsonValidationErrors('company_id');

        expect($vehicle->fresh()->company_id)->not->toBe($target->id);
    })->with(['asset group', 'service log', 'maintenance schedule']);

    it('refuses to move a trailer that is in an asset group', function () {
        $group = AssetGroupFactory::new()->withTrailer()->create();
        $trailer = $group->trailer()->firstOrFail();
        $target = Actors::companyOf(Actors::companyOwner());

        $this->actingAs(Actors::superAdmin())
            ->putJson(route('admin.trailer.update', $trailer->id), FleetAssets::trailerFormData($trailer, ['company_id' => $target->id]))
            ->assertStatus(422)->assertJsonValidationErrors('company_id');

        expect($trailer->fresh()->company_id)->not->toBe($target->id);
    });

    it('still moves an asset that has no company records', function (string $kind) {
        $asset = FleetAssets::assetOf(Actors::companyOwner(), $kind);
        $target = Actors::companyOf(Actors::companyOwner());

        $this->actingAs(Actors::superAdmin())
            ->putJson(route("admin.$kind.update", $asset->id), FleetAssets::formData($asset, ['company_id' => $target->id]))
            ->assertOk();

        expect($asset->fresh()->company_id)->toBe($target->id);
    })->with('fleet assets');

    it('still lets a super-admin edit an asset with records when the company stays the same', function () {
        $vehicle = VehicleFactory::new()->create();
        ServiceLogFactory::new()->forVehicle($vehicle)->create();

        $this->actingAs(Actors::superAdmin())
            ->putJson(route('admin.vehicle.update', $vehicle->id), FleetAssets::vehicleFormData($vehicle, ['make' => 'Renamed']))
            ->assertOk();

        expect($vehicle->fresh()->make)->toBe('Renamed');
    });
});

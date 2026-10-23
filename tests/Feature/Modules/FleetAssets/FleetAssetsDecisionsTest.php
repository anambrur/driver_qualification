<?php

use App\Models\AssetGroup;
use App\Models\Vehicle;
use Database\Factories\AssetGroupFactory;
use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| Product decisions of 2026-10-11 (docs/testing/PRODUCT_DECISIONS.md):
| PO-05  vehicles have an engine_hours field on the vehicle form.
| PO-22  fleet-assets follow-ups: VINs never contain I, O or Q; the vehicle details no longer
|        return the non-existent license_plate; deleting a vehicle ends its asset group.
*/

describe('PO-05: engine hours on the vehicle form', function () {
    it('saves engine hours on create and update', function () {
        $owner = Actors::companyOwner();

        $this->actingAs($owner)
            ->postJson(route('admin.vehicle.store'), FleetAssets::vehiclePayload(['engine_hours' => 1500]))
            ->assertOk();
        $vehicle = Vehicle::withoutTenancy()->sole();
        expect($vehicle->engine_hours)->toBe(1500);

        $this->actingAs($owner)
            ->putJson(route('admin.vehicle.update', $vehicle->id), FleetAssets::vehicleFormData($vehicle, ['engine_hours' => 1600]))
            ->assertOk();
        expect($vehicle->fresh()->engine_hours)->toBe(1600);
    });

    it('may be left empty', function () {
        $this->actingAs(Actors::companyOwner())
            ->postJson(route('admin.vehicle.store'), FleetAssets::vehiclePayload(['engine_hours' => '']))
            ->assertOk();

        expect(Vehicle::withoutTenancy()->sole()->engine_hours)->toBeNull();
    });

    it('rejects negative or non-numeric engine hours', function (mixed $value) {
        $this->actingAs(Actors::companyOwner())
            ->postJson(route('admin.vehicle.store'), FleetAssets::vehiclePayload(['engine_hours' => $value]))
            ->assertStatus(422)->assertJsonValidationErrors('engine_hours', 'errors');
    })->with([-1, 'lots']);

    it('shows the field on the vehicle form', function () {
        $this->actingAs(Actors::companyOwner())->get(route('admin.vehicle.index'))
            ->assertOk()->assertSee('name="engine_hours"', false);
    });
});

describe('PO-22: fleet-assets follow-ups', function () {
    it('rejects a VIN with I, O or Q on create and update', function (string $kind, string $letter) {
        $owner = Actors::companyOwner();
        $vin = '1HGCM82633A00435'.$letter;

        $this->actingAs($owner)
            ->postJson(route("admin.$kind.store"), FleetAssets::payload($kind, ['vin' => $vin]))
            ->assertStatus(422)->assertJsonValidationErrors('vin', 'errors');

        $asset = FleetAssets::assetOf($owner, $kind);
        $this->actingAs($owner)
            ->putJson(route("admin.$kind.update", $asset->id), FleetAssets::formData($asset, ['vin' => $vin]))
            ->assertStatus(422)->assertJsonValidationErrors('vin', 'errors');
    })->with(['vehicle', 'trailer'])->with(['I', 'O', 'Q', 'o']);

    it('still accepts a valid VIN', function (string $kind) {
        $this->actingAs(Actors::companyOwner())
            ->postJson(route("admin.$kind.store"), FleetAssets::payload($kind, ['vin' => '1HGCM82633A004352']))
            ->assertOk();
    })->with(['vehicle', 'trailer']);

    it('does not return license_plate in the vehicle details', function () {
        $owner = Actors::companyOwner();
        $vehicle = FleetAssets::vehicleOf($owner);

        $data = $this->actingAs($owner)->getJson(route('admin.vehicles.get-vehicle-details', $vehicle->id))->assertOk()->json('data');

        expect($data)->not->toHaveKey('license_plate')->and($data['vin'])->toBe($vehicle->vin);
    });

    it('ends the asset group of a deleted vehicle', function () {
        $owner = Actors::companyOwner();
        $vehicle = FleetAssets::vehicleOf($owner);
        $group = AssetGroupFactory::new()->create(['vehicle_id' => $vehicle->id, 'status' => 'active']);

        $this->actingAs($owner)->deleteJson(route('admin.vehicle.destroy', $vehicle->id))->assertOk();

        expect(AssetGroup::withoutTenancy()->findOrFail($group->id)->status)->toBe('inactive');
    });
});

describe('04 §7: an asset transferred out frees its unit number and VIN', function () {
    it('accepts the identifiers of a transferred-out asset but not of a deleted one', function (string $kind) {
        $owner = Actors::companyOwner();
        $asset = FleetAssets::assetOf($owner, $kind);
        $identifiers = $asset->only(['unit_no', 'vin']);

        $asset->delete();
        $this->actingAs($owner)
            ->postJson(route("admin.$kind.store"), FleetAssets::payload($kind, $identifiers))
            ->assertStatus(422)->assertJsonValidationErrors(['unit_no', 'vin'], 'errors');

        $asset->forceFill(['transferred_out_at' => now()])->save();
        $this->actingAs($owner)
            ->postJson(route("admin.$kind.store"), FleetAssets::payload($kind, $identifiers))
            ->assertOk();
    })->with(['vehicle', 'trailer']);
});

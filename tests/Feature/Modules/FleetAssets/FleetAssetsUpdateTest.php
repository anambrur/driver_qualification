<?php

use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| FLT-03: update() saved $request->all(). $fillable keeps out id, timestamps and deleted_at,
| and a tenant's company_id is overwritten with the asset's own, so a tenant cannot move or
| tamper with an asset. update() now saves only the validated fields.
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

        $fresh = FleetAssets::model($kind)::withoutTenancy()->withTrashed()->findOrFail($asset->id);
        expect($fresh->company_id)->toBe($asset->company_id)
            ->and($fresh->created_at->toDateTimeString())->toBe($createdAt)
            ->and($fresh->deleted_at)->toBeNull()
            ->and($fresh->make)->toBe('Renamed');
    })->with('fleet assets');

    it('returns 404 for another tenant\'s asset', function (string $kind) {
        $asset = FleetAssets::assetOf(Actors::companyOwner(), $kind);
        $intruder = Actors::companyOwner();

        $this->actingAs($intruder)
            ->putJson(route("admin.$kind.update", $asset->id), FleetAssets::formData($asset, ['make' => 'Hacked']))
            ->assertNotFound();
        $this->actingAs($intruder)->getJson(route("admin.$kind.edit", $asset->id))->assertNotFound();
        $this->actingAs($intruder)->deleteJson(route("admin.$kind.destroy", $asset->id))->assertNotFound();

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

// Moving an asset to another company (PO-08) is the platform's audited Asset Transfer now:
// the target gets copies, the source keeps its history. See Platform/AssetTransferTest.

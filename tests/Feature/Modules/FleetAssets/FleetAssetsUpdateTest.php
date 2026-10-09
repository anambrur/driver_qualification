<?php

use Database\Factories\AssetGroupFactory;
use Database\Factories\CompanyFactory;
use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\ServiceLogFactory;
use Database\Factories\VehicleFactory;
use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| FLT-03: update() saved $request->all() (and the trailer store() created from it). A
| super-admin could move an asset to another company while its asset groups, service logs and
| maintenance schedules, which carry their own company_id, stayed with the old company.
*/

describe('FLT-03: only validated fields are saved', function () {
    it('ignores company_id and unlisted columns posted by a tenant', function (string $assetType) {
        [$tenant, $asset] = FleetAssets::tenantWithAsset($assetType);
        $otherCompany = CompanyFactory::new()->create();

        $this->actingAs($tenant)
            ->putJson(FleetAssets::route($assetType, 'update', $asset->id), FleetAssets::payload($assetType, [
                'company_id' => $otherCompany->id,
                'id' => 999999,
                'deleted_at' => now()->toDateTimeString(),
                'created_at' => '2000-01-01 00:00:00',
                'make' => 'Kenworth',
            ]))
            ->assertOk();

        $fresh = FleetAssets::model($assetType)::withTrashed()->findOrFail($asset->id);
        expect($fresh->company_id)->toBe($asset->company_id)
            ->and($fresh->make)->toBe('Kenworth')
            ->and($fresh->deleted_at)->toBeNull()
            ->and($fresh->created_at->year)->not->toBe(2000);
    })->with(FleetAssets::TYPES);

    it('files a tenant\'s new asset under the tenant\'s company whatever company_id is posted', function (string $assetType) {
        $tenant = Actors::companyOwner();
        $otherCompany = CompanyFactory::new()->create();

        $id = $this->actingAs($tenant)
            ->postJson(FleetAssets::route($assetType, 'store'), FleetAssets::payload($assetType, [
                'company_id' => $otherCompany->id,
                'deleted_at' => now()->toDateTimeString(),
            ]))
            ->assertOk()
            ->json('data.id');

        $asset = FleetAssets::model($assetType)::withTrashed()->findOrFail($id);
        expect($asset->company_id)->toBe(Actors::companyOf($tenant)->id)
            ->and($asset->deleted_at)->toBeNull();
    })->with(FleetAssets::TYPES);

    it('still returns 403 for another tenant\'s asset and changes nothing', function (string $assetType) {
        [, $asset] = FleetAssets::tenantWithAsset($assetType);

        $this->actingAs(Actors::companyOwner())
            ->putJson(FleetAssets::route($assetType, 'update', $asset->id), FleetAssets::payload($assetType, ['make' => 'Hijacked']))
            ->assertForbidden();

        expect($asset->fresh()->make)->not->toBe('Hijacked');
    })->with(FleetAssets::TYPES);
});

describe('FLT-03: a super-admin moving an asset between companies', function () {
    it('refuses to move a vehicle that still has company records attached', function (string $dependent) {
        [, $vehicle] = FleetAssets::tenantWithAsset('vehicle');
        match ($dependent) {
            'asset group' => AssetGroupFactory::new()->create(['vehicle_id' => $vehicle->id]),
            'service log' => ServiceLogFactory::new()->forVehicle($vehicle)->create(),
            'maintenance schedule' => MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create(),
        };
        $target = CompanyFactory::new()->create();

        $this->actingAs(Actors::superAdmin())
            ->putJson(FleetAssets::route('vehicle', 'update', $vehicle->id), FleetAssets::payload('vehicle', [
                'company_id' => $target->id,
                'make' => 'Moved',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);

        expect($vehicle->fresh()->company_id)->toBe($vehicle->company_id)
            ->and($vehicle->fresh()->make)->not->toBe('Moved');
    })->with(['asset group', 'service log', 'maintenance schedule']);

    it('refuses to move a trailer that is in an asset group', function () {
        [, $trailer] = FleetAssets::tenantWithAsset('trailer');
        $vehicle = VehicleFactory::new()->create(['company_id' => $trailer->company_id]);
        AssetGroupFactory::new()->create(['vehicle_id' => $vehicle->id, 'trailer_id' => $trailer->id]);
        $target = CompanyFactory::new()->create();

        $this->actingAs(Actors::superAdmin())
            ->putJson(FleetAssets::route('trailer', 'update', $trailer->id), FleetAssets::payload('trailer', [
                'company_id' => $target->id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['company_id']);

        expect($trailer->fresh()->company_id)->toBe($trailer->company_id);
    });

    it('counts soft-deleted asset groups too', function () {
        [, $vehicle] = FleetAssets::tenantWithAsset('vehicle');
        AssetGroupFactory::new()->create(['vehicle_id' => $vehicle->id])->delete();
        $target = CompanyFactory::new()->create();

        $this->actingAs(Actors::superAdmin())
            ->putJson(FleetAssets::route('vehicle', 'update', $vehicle->id), FleetAssets::payload('vehicle', [
                'company_id' => $target->id,
            ]))
            ->assertStatus(422);
    });

    it('still moves an asset with no company records attached', function (string $assetType) {
        [, $asset] = FleetAssets::tenantWithAsset($assetType);
        $target = CompanyFactory::new()->create();

        $this->actingAs(Actors::superAdmin())
            ->putJson(FleetAssets::route($assetType, 'update', $asset->id), FleetAssets::payload($assetType, [
                'company_id' => $target->id,
            ]))
            ->assertOk();

        expect($asset->fresh()->company_id)->toBe($target->id);
    })->with(FleetAssets::TYPES);

    it('still lets a super-admin edit an asset with records when the company does not change', function () {
        [, $vehicle] = FleetAssets::tenantWithAsset('vehicle');
        ServiceLogFactory::new()->forVehicle($vehicle)->create();

        $this->actingAs(Actors::superAdmin())
            ->putJson(FleetAssets::route('vehicle', 'update', $vehicle->id), FleetAssets::payload('vehicle', [
                'company_id' => $vehicle->company_id,
                'make' => 'Volvo',
            ]))
            ->assertOk();

        expect($vehicle->fresh()->make)->toBe('Volvo');
    });
});

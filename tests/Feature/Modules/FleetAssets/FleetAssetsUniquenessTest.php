<?php

use Database\Factories\CompanyFactory;
use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| FLT-02: unit_no and vin were `unique:vehicles,…` / `unique:trailers,…` across every company,
| so tenant B could not reuse tenant A's unit number (and the error told B it exists).
*/

describe('FLT-02: unit numbers and VINs are unique within a company', function () {
    it('lets two tenants use the same unit number and VIN', function (string $assetType) {
        [, $other] = FleetAssets::tenantWithAsset($assetType, ['unit_no' => 'T001', 'vin' => '1FUJGLDR5CLBP8834']);
        $tenant = Actors::companyOwner();

        $this->actingAs($tenant)
            ->postJson(FleetAssets::route($assetType, 'store'), FleetAssets::payload($assetType, [
                'unit_no' => 'T001',
                'vin' => '1FUJGLDR5CLBP8834',
            ]))
            ->assertOk()
            ->assertJson(['success' => true]);

        expect(FleetAssets::model($assetType)::where('unit_no', 'T001')->pluck('company_id')->sort()->values()->all())
            ->toBe(collect([$other->company_id, Actors::companyOf($tenant)->id])->sort()->values()->all());
    })->with(FleetAssets::TYPES);

    it('lets a tenant rename an asset to a unit number another tenant uses', function (string $assetType) {
        FleetAssets::tenantWithAsset($assetType, ['unit_no' => 'T001', 'vin' => '1FUJGLDR5CLBP8834']);
        [$tenant, $asset] = FleetAssets::tenantWithAsset($assetType);

        $this->actingAs($tenant)
            ->putJson(FleetAssets::route($assetType, 'update', $asset->id), FleetAssets::payload($assetType, [
                'unit_no' => 'T001',
                'vin' => '1FUJGLDR5CLBP8834',
            ]))
            ->assertOk();

        expect($asset->fresh()->unit_no)->toBe('T001');
    })->with(FleetAssets::TYPES);

    it('still rejects a duplicate unit number and VIN within one company, deleted assets included', function (string $assetType, bool $deleted) {
        [$tenant, $asset] = FleetAssets::tenantWithAsset($assetType, ['unit_no' => 'T001', 'vin' => '1FUJGLDR5CLBP8834']);
        if ($deleted) {
            $asset->delete();
        }

        $this->actingAs($tenant)
            ->postJson(FleetAssets::route($assetType, 'store'), FleetAssets::payload($assetType, [
                'unit_no' => 'T001',
                'vin' => '1FUJGLDR5CLBP8834',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_no', 'vin']);
    })->with(FleetAssets::TYPES)->with(['active' => false, 'deleted' => true]);

    it('lets an asset keep its own unit number on edit, but not take a sibling\'s', function (string $assetType) {
        [$tenant, $asset] = FleetAssets::tenantWithAsset($assetType);
        $sibling = FleetAssets::assetOf($tenant, $assetType);

        $this->actingAs($tenant)
            ->putJson(FleetAssets::route($assetType, 'update', $asset->id), FleetAssets::payload($assetType, [
                'unit_no' => $asset->unit_no,
                'vin' => $asset->vin,
            ]))
            ->assertOk();

        $this->putJson(FleetAssets::route($assetType, 'update', $asset->id), FleetAssets::payload($assetType, [
            'unit_no' => $sibling->unit_no,
            'vin' => $sibling->vin,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_no', 'vin']);
    })->with(FleetAssets::TYPES);

    it('checks a super-admin\'s new asset against the chosen company', function (string $assetType) {
        [, $existing] = FleetAssets::tenantWithAsset($assetType, ['unit_no' => 'T001', 'vin' => '1FUJGLDR5CLBP8834']);
        $otherCompany = CompanyFactory::new()->create();
        $payload = FleetAssets::payload($assetType, ['unit_no' => 'T001', 'vin' => '1FUJGLDR5CLBP8834']);

        $this->actingAs(Actors::superAdmin())
            ->postJson(FleetAssets::route($assetType, 'store'), $payload + ['company_id' => $existing->company_id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_no', 'vin']);

        $this->postJson(FleetAssets::route($assetType, 'store'), $payload + ['company_id' => $otherCompany->id])
            ->assertOk();
    })->with(FleetAssets::TYPES);

    it('checks a super-admin\'s move against the target company', function (string $assetType) {
        [, $existing] = FleetAssets::tenantWithAsset($assetType, ['unit_no' => 'T001', 'vin' => '1FUJGLDR5CLBP8834']);
        [, $asset] = FleetAssets::tenantWithAsset($assetType, ['unit_no' => 'T001', 'vin' => '1FUJGLDR5CLBP8834']);

        $this->actingAs(Actors::superAdmin())
            ->putJson(FleetAssets::route($assetType, 'update', $asset->id), FleetAssets::payload($assetType, [
                'unit_no' => 'T001',
                'vin' => '1FUJGLDR5CLBP8834',
                'company_id' => $existing->company_id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_no', 'vin']);
    })->with(FleetAssets::TYPES);
});

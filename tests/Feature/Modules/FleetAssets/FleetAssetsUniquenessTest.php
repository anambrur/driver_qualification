<?php

use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| FLT-02: unit_no and vin were unique across every tenant (validation rule and DB index).
| Tenant B could not add a unit "101" because tenant A has one, and the 422 told B that
| another company has that unit number or VIN. They are now unique within a company.
*/

dataset('fleet assets', [
    'vehicles' => ['vehicle'],
    'trailers' => ['trailer'],
]);

describe('FLT-02: unit no and VIN are unique per company', function () {
    it('lets another tenant use the same unit no and VIN (VIN per company, decided PO-10, 2026-10-11)', function (string $kind) {
        $existing = FleetAssets::assetOf(Actors::companyOwner(), $kind, ['unit_no' => '101', 'vin' => '1HGCM82633A004352']);
        $other = Actors::companyOwner();

        $this->actingAs($other)
            ->postJson(route("admin.$kind.store"), FleetAssets::payload($kind, ['unit_no' => '101', 'vin' => '1HGCM82633A004352']))
            ->assertOk()->assertJson(['success' => true]);

        expect(FleetAssets::model($kind)::withoutTenancy()->where('unit_no', '101')->pluck('company_id')->sort()->values()->all())
            ->toBe(collect([$existing->company_id, Actors::companyOf($other)->id])->sort()->values()->all());
    })->with('fleet assets');

    it('lets another tenant rename an asset to a unit no and VIN used elsewhere', function (string $kind) {
        FleetAssets::assetOf(Actors::companyOwner(), $kind, ['unit_no' => '101', 'vin' => '1HGCM82633A004352']);
        $other = Actors::companyOwner();
        $mine = FleetAssets::assetOf($other, $kind);

        $this->actingAs($other)
            ->putJson(route("admin.$kind.update", $mine->id), FleetAssets::formData($mine, ['unit_no' => '101', 'vin' => '1HGCM82633A004352']))
            ->assertOk();

        expect($mine->fresh()->unit_no)->toBe('101');
    })->with('fleet assets');

    it('lets another tenant add a unit no another company uses', function (string $kind) {
        FleetAssets::assetOf(Actors::companyOwner(), $kind, ['unit_no' => '101']);
        $owner = Actors::companyOwner();
        $target = Actors::companyOf($owner);

        $this->actingAs($owner)
            ->postJson(route("admin.$kind.store"), FleetAssets::payload($kind, ['unit_no' => '101']))
            ->assertOk();

        expect(FleetAssets::model($kind)::withoutTenancy()->where('company_id', $target->id)->where('unit_no', '101')->exists())->toBeTrue();
    })->with('fleet assets');

    it('still rejects a duplicate unit no or VIN within the company', function (string $kind, string $field) {
        $owner = Actors::companyOwner();
        $existing = FleetAssets::assetOf($owner, $kind);

        $this->actingAs($owner)
            ->postJson(route("admin.$kind.store"), FleetAssets::payload($kind, [$field => $existing->$field]))
            ->assertStatus(422)->assertJsonValidationErrors($field);

        $mine = FleetAssets::assetOf($owner, $kind);
        $this->actingAs($owner)
            ->putJson(route("admin.$kind.update", $mine->id), FleetAssets::formData($mine, [$field => $existing->$field]))
            ->assertStatus(422)->assertJsonValidationErrors($field);
    })->with('fleet assets')->with(['unit_no', 'vin']);

    it('rejects a unit no of the company\'s own deleted asset with a 422, not a 500', function (string $kind) {
        $owner = Actors::companyOwner();
        FleetAssets::assetOf($owner, $kind, ['unit_no' => '101'])->delete();

        $this->actingAs($owner)
            ->postJson(route("admin.$kind.store"), FleetAssets::payload($kind, ['unit_no' => '101']))
            ->assertStatus(422)->assertJsonValidationErrors('unit_no');
    })->with('fleet assets');

    it('still lets an asset keep its own unit no and VIN on update', function (string $kind) {
        $owner = Actors::companyOwner();
        $mine = FleetAssets::assetOf($owner, $kind);

        $this->actingAs($owner)
            ->putJson(route("admin.$kind.update", $mine->id), FleetAssets::formData($mine, ['make' => 'Renamed']))
            ->assertOk();

        expect($mine->fresh()->make)->toBe('Renamed');
    })->with('fleet assets');

});

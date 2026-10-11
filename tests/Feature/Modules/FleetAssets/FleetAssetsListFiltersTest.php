<?php

use App\Models\EquipmentType;
use App\Models\VehicleType;
use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| FLT-04: the list query never included soft-deleted rows, so the "Deleted" status filter
| showed nothing and the Restore button never rendered. Deleted assets are now listed under
| the "Deleted" filter, with Restore and without Edit (edit() 404s on a deleted asset).
|
| FLT-05 (found 2026-10-10): the pages send search_text and the type filter, but the
| controllers ignored both, so "Quick Search" and the type filter did nothing.
*/

dataset('fleet assets', [
    'vehicles' => ['vehicle'],
    'trailers' => ['trailer'],
]);

function fltList($test, $user, string $kind, array $params = []): array
{
    return $test->actingAs($user)->withHeaders(FleetAssets::ajax())
        ->get(route("admin.$kind.index", ['draw' => 1] + $params))
        ->assertOk()->json('data');
}

describe('FLT-04: deleted assets can be listed and restored', function () {
    it('lists deleted assets under the Deleted filter with a Restore button', function (string $kind) {
        $owner = Actors::companyOwner();
        $deleted = FleetAssets::assetOf($owner, $kind, ['unit_no' => 'GONE-1']);
        $deleted->delete();
        FleetAssets::assetOf($owner, $kind, ['unit_no' => 'LIVE-1']);

        $rows = fltList($this, $owner, $kind, ['status' => 'deleted']);

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['id'])->toBe($deleted->id)
            ->and($rows[0]['status'])->toContain('Deleted')
            ->and($rows[0]['action'])->toContain('data-action="restore"')
            ->and($rows[0]['action'])->not->toContain('data-action="edit"')
            ->and($rows[0]['action'])->not->toContain('data-action="delete"');
    })->with('fleet assets');

    it('keeps deleted assets out of the Active list', function (string $kind) {
        $owner = Actors::companyOwner();
        FleetAssets::assetOf($owner, $kind)->delete();
        $live = FleetAssets::assetOf($owner, $kind);

        $rows = fltList($this, $owner, $kind, ['status' => 'active']);

        expect(array_column($rows, 'id'))->toBe([$live->id])
            ->and($rows[0]['action'])->toContain('data-action="edit"')
            ->and($rows[0]['action'])->toContain('data-action="delete"');
    })->with('fleet assets');

    it('lists live and deleted assets under All Status, the deleted ones with their badge and Restore (decided PO-09)', function (string $kind, string $status) {
        $owner = Actors::companyOwner();
        $deleted = FleetAssets::assetOf($owner, $kind, ['unit_no' => 'A-GONE']);
        $deleted->delete();
        $live = FleetAssets::assetOf($owner, $kind, ['unit_no' => 'B-LIVE']);
        FleetAssets::assetOf(Actors::companyOwner(), $kind)->delete();

        $response = $this->actingAs($owner)->withHeaders(FleetAssets::ajax())
            ->get(route("admin.$kind.index", ['draw' => 1, 'status' => $status]))->assertOk();
        $rows = collect($response->json('data'))->keyBy('id');

        expect($rows->keys()->sort()->values()->all())->toBe(collect([$deleted->id, $live->id])->sort()->values()->all())
            ->and($response->json('recordsTotal'))->toBe(2)
            ->and($rows[$deleted->id]['status'])->toContain('Deleted')
            ->and($rows[$deleted->id]['action'])->toContain('data-action="restore"')
            ->and($rows[$live->id]['action'])->toContain('data-action="edit"');
    })->with('fleet assets')->with(['empty' => '', 'all' => 'all']);

    it('does not list another tenant\'s deleted assets', function (string $kind) {
        FleetAssets::assetOf(Actors::companyOwner(), $kind)->delete();

        expect(fltList($this, Actors::companyOwner(), $kind, ['status' => 'deleted']))->toBe([]);
    })->with('fleet assets');

    // One actor per test: the controller memoizes the company context and the test reuses it.
    it('restores the tenant\'s own asset', function (string $kind) {
        $owner = Actors::companyOwner();
        $asset = FleetAssets::assetOf($owner, $kind);
        $asset->delete();

        $this->actingAs($owner)->postJson(route("admin.$kind.restore", $asset->id))->assertOk();
        expect($asset->fresh()->trashed())->toBeFalse();
    })->with('fleet assets');

    it('refuses to restore another tenant\'s asset', function (string $kind) {
        $asset = FleetAssets::assetOf(Actors::companyOwner(), $kind);
        $asset->delete();

        $this->actingAs(Actors::companyOwner())->postJson(route("admin.$kind.restore", $asset->id))->assertForbidden();
        expect($asset->fresh()->trashed())->toBeTrue();
    })->with('fleet assets');
});

describe('FLT-05: the quick search and type filter', function () {
    it('filters by the quick search text', function (string $kind, string $field) {
        $owner = Actors::companyOwner();
        $match = FleetAssets::assetOf($owner, $kind, [$field => 'NEEDLE-42'.($field === 'vin' ? 'ABCDEFGH' : '')]);
        FleetAssets::assetOf($owner, $kind, ['unit_no' => 'OTHER-1', 'make' => 'Volvo', 'model' => 'VNL']);

        $rows = fltList($this, $owner, $kind, ['search_text' => 'needle-42']);

        expect(array_column($rows, 'id'))->toBe([$match->id]);
    })->with('fleet assets')->with(['unit_no', 'vin', 'make', 'model']);

    it('keeps the quick search inside the tenant', function (string $kind) {
        FleetAssets::assetOf(Actors::companyOwner(), $kind, ['unit_no' => 'NEEDLE-1']);
        $owner = Actors::companyOwner();
        FleetAssets::assetOf($owner, $kind, ['unit_no' => 'MINE-1']);

        expect(fltList($this, $owner, $kind, ['search_text' => 'NEEDLE']))->toBe([]);
    })->with('fleet assets');

    it('filters vehicles by type', function () {
        $owner = Actors::companyOwner();
        $reefer = VehicleType::create(['name' => 'Tractor']);
        $match = FleetAssets::vehicleOf($owner, ['vehicle_type_id' => $reefer->id]);
        FleetAssets::vehicleOf($owner, ['vehicle_type_id' => VehicleType::create(['name' => 'Box truck'])->id]);

        expect(array_column(fltList($this, $owner, 'vehicle', ['vehicle_type_id' => $reefer->id]), 'id'))->toBe([$match->id]);
    });

    it('filters trailers by equipment type', function () {
        $owner = Actors::companyOwner();
        $flatbed = EquipmentType::create(['name' => 'Flatbed']);
        $match = FleetAssets::trailerOf($owner, ['equipment_types_id' => $flatbed->id]);
        FleetAssets::trailerOf($owner);

        expect(array_column(fltList($this, $owner, 'trailer', ['equipment_types_id' => $flatbed->id]), 'id'))->toBe([$match->id]);
    });

    it('lists everything when the filters are empty', function (string $kind) {
        $owner = Actors::companyOwner();
        FleetAssets::assetOf($owner, $kind);
        FleetAssets::assetOf($owner, $kind);

        expect(fltList($this, $owner, $kind, ['search_text' => '', 'vehicle_type_id' => '', 'equipment_types_id' => '', 'status' => '']))->toHaveCount(2);
    })->with('fleet assets');
});

it('requires the vehicle and trailer permissions', function (string $kind) {
    $user = Actors::plainUser();

    $this->actingAs($user)->withHeaders(FleetAssets::ajax())->get(route("admin.$kind.index"))->assertForbidden();
    $this->actingAs($user)->postJson(route("admin.$kind.store"), FleetAssets::payload($kind))->assertForbidden();
})->with('fleet assets');

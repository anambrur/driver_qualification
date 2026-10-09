<?php

use App\Models\EquipmentType;
use App\Models\VehicleType;
use Tests\Feature\Modules\FleetAssets\FleetAssets;
use Tests\Support\Actors;

/*
| FLT-04: the page sends status / search_text / type filters, but index() ignored them and
| never included soft-deleted rows, so the "Deleted" filter showed nothing and the Restore
| button never rendered.
*/

function fleetAssetList(string $assetType, array $filters = []): array
{
    return test()->getJson(FleetAssets::route($assetType, 'index', $filters), FleetAssets::AJAX)
        ->assertOk()
        ->json('data');
}

describe('FLT-04: list filters and Restore', function () {
    it('lists only deleted assets, with a Restore button, when filtering by Deleted', function (string $assetType) {
        [$tenant, $live] = FleetAssets::tenantWithAsset($assetType);
        $deleted = FleetAssets::assetOf($tenant, $assetType);
        $deleted->delete();

        $this->actingAs($tenant);
        $rows = fleetAssetList($assetType, ['status' => 'deleted']);

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['id'])->toBe($deleted->id)
            ->and($rows[0]['status'])->toContain('Deleted')
            ->and($rows[0]['action'])->toContain('data-action="restore"')
            ->not->toContain('data-action="delete"');
    })->with(FleetAssets::TYPES);

    it('leaves deleted assets out of the default and Active lists', function (string $assetType) {
        [$tenant, $live] = FleetAssets::tenantWithAsset($assetType);
        FleetAssets::assetOf($tenant, $assetType)->delete();

        $this->actingAs($tenant);

        foreach ([[], ['status' => 'active']] as $filters) {
            $rows = fleetAssetList($assetType, $filters);
            expect(array_column($rows, 'id'))->toBe([$live->id]);
        }
    })->with(FleetAssets::TYPES);

    it('does not show another tenant\'s deleted assets', function (string $assetType) {
        [, $other] = FleetAssets::tenantWithAsset($assetType);
        $other->delete();

        $this->actingAs(Actors::companyOwner());

        expect(fleetAssetList($assetType, ['status' => 'deleted']))->toBe([]);
    })->with(FleetAssets::TYPES);

    it('restores an asset from the Deleted list', function (string $assetType) {
        [$tenant, $asset] = FleetAssets::tenantWithAsset($assetType);
        $asset->delete();

        $this->actingAs($tenant)
            ->postJson(FleetAssets::route($assetType, 'restore', $asset->id))
            ->assertOk();

        expect($asset->fresh()->deleted_at)->toBeNull();
    })->with(FleetAssets::TYPES);

    it('still refuses to restore another tenant\'s asset', function (string $assetType) {
        [, $asset] = FleetAssets::tenantWithAsset($assetType);
        $asset->delete();

        $this->actingAs(Actors::companyOwner())
            ->postJson(FleetAssets::route($assetType, 'restore', $asset->id))
            ->assertForbidden();

        expect($asset->fresh()->deleted_at)->not->toBeNull();
    })->with(FleetAssets::TYPES);

    it('applies the quick search within the tenant\'s own assets', function (string $assetType) {
        [$tenant, $match] = FleetAssets::tenantWithAsset($assetType, ['make' => 'Zephyrmatic']);
        FleetAssets::assetOf($tenant, $assetType, ['make' => 'Volvo']);
        FleetAssets::tenantWithAsset($assetType, ['make' => 'Zephyrmatic']);

        $this->actingAs($tenant);

        expect(array_column(fleetAssetList($assetType, ['search_text' => 'zephyr']), 'id'))->toBe([$match->id]);
    })->with(FleetAssets::TYPES);

    it('filters vehicles by type', function () {
        $type = VehicleType::create(['name' => 'Tractor']);
        [$tenant, $match] = FleetAssets::tenantWithAsset('vehicle', ['vehicle_type_id' => $type->id]);
        FleetAssets::assetOf($tenant, 'vehicle');

        $this->actingAs($tenant);

        expect(array_column(fleetAssetList('vehicle', ['vehicle_type_id' => $type->id]), 'id'))->toBe([$match->id]);
    });

    it('filters trailers by equipment type', function () {
        $type = EquipmentType::create(['name' => 'Reefer']);
        [$tenant, $match] = FleetAssets::tenantWithAsset('trailer', ['equipment_types_id' => $type->id]);
        FleetAssets::assetOf($tenant, 'trailer');

        $this->actingAs($tenant);

        expect(array_column(fleetAssetList('trailer', ['equipment_types_id' => $type->id]), 'id'))->toBe([$match->id]);
    });

    // One user per test: the route caches its controller, and CompanyFilterTrait memoizes the company.
    it('lists only the tenant\'s own assets', function (string $assetType) {
        [$tenant, $own] = FleetAssets::tenantWithAsset($assetType);
        FleetAssets::tenantWithAsset($assetType);

        $this->actingAs($tenant);

        expect(array_column(fleetAssetList($assetType), 'id'))->toBe([$own->id]);
    })->with(FleetAssets::TYPES);

    it('shows a super-admin every company\'s assets', function (string $assetType) {
        [, $first] = FleetAssets::tenantWithAsset($assetType);
        [, $second] = FleetAssets::tenantWithAsset($assetType);

        $this->actingAs(Actors::superAdmin());

        expect(array_column(fleetAssetList($assetType), 'id'))->toEqualCanonicalizing([$first->id, $second->id]);
    })->with(FleetAssets::TYPES);

    it('requires the vehicle and trailer permissions', function (string $assetType) {
        $this->actingAs(Actors::plainUser())
            ->getJson(FleetAssets::route($assetType, 'index'), FleetAssets::AJAX)
            ->assertForbidden();
    })->with(FleetAssets::TYPES);
});

<?php

use Database\Factories\AssetGroupFactory;
use Tests\Feature\Modules\AssetGroups\AssetGroupActors;

/*
| Follow-up: the page sent `status` and `search_text`, but the server ignored both, so the
| "Deleted" filter showed nothing and the Restore button could never appear.
*/

function agrList(array $filters = []): \Illuminate\Testing\TestResponse
{
    return test()->getJson(route('admin.asset-group.index', $filters), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk();
}

describe('list filters', function () {
    it('lists only deleted groups, with just a Restore button, when filtering by Deleted', function () {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        AssetGroupFactory::new()->forCompany($company)->create();
        $deleted = AssetGroupFactory::new()->forCompany($company)->create(['group_name' => "x' onmouseover=\"alert(1)"]);
        $deleted->delete();
        AssetGroupFactory::new()->create()->delete();

        $this->actingAs($tenant);
        $response = agrList(['status' => 'deleted']);

        expect($response->json('recordsTotal'))->toBe(1)
            ->and($response->json('data.0.id'))->toBe($deleted->id)
            ->and($response->json('data.0.status'))->toContain('Deleted')
            ->and($response->json('data.0.action'))
            ->toContain('data-action="restore"')
            ->toContain('data-name="'.e($deleted->group_name).'"')
            ->not->toContain($deleted->group_name)
            ->not->toContain('editAssetGroup')
            ->not->toContain('data-action="delete"');
    });

    it('leaves deleted groups out of the default list', function () {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        $active = AssetGroupFactory::new()->forCompany($company)->create();
        AssetGroupFactory::new()->forCompany($company)->create()->delete();

        $this->actingAs($tenant);

        expect(agrList()->json('data.*.id'))->toBe([$active->id]);
    });

    it('filters by active and inactive status', function (string $status) {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        $match = AssetGroupFactory::new()->forCompany($company)->create(['status' => $status]);
        AssetGroupFactory::new()->forCompany($company)->create(['status' => $status === 'active' ? 'inactive' : 'active']);

        $this->actingAs($tenant);

        expect(agrList(['status' => $status])->json('data.*.id'))->toBe([$match->id]);
    })->with(['active', 'inactive']);

    it('applies the quick search within the tenant\'s own groups', function () {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        $match = AssetGroupFactory::new()->forCompany($company)->create(['primary_driver_name' => 'Ada Lovelace']);
        AssetGroupFactory::new()->forCompany($company)->create(['primary_driver_name' => 'Grace Hopper']);
        AssetGroupFactory::new()->create(['primary_driver_name' => 'Ada Foreign']);

        $this->actingAs($tenant);

        expect(agrList(['search_text' => 'Ada'])->json('data.*.id'))->toBe([$match->id]);
    });

    it('restores a group from the Deleted list', function () {
        $tenant = AssetGroupActors::tenant();
        $group = AssetGroupFactory::new()->forCompany(AssetGroupActors::company($tenant))->create();
        $group->delete();

        $this->actingAs($tenant)->postJson(route('admin.asset-group.restore', $group->id))->assertOk();

        expect(agrList(['status' => 'deleted'])->json('recordsTotal'))->toBe(0)
            ->and(agrList()->json('data.*.id'))->toBe([$group->id]);
    });
});

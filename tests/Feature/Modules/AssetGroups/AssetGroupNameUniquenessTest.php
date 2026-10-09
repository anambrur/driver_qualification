<?php

use App\Models\AssetGroup;
use Database\Factories\AssetGroupFactory;
use Database\Factories\DriverFactory;
use Database\Factories\VehicleFactory;
use Tests\Feature\Modules\AssetGroups\AssetGroupActors;
use Tests\Support\Actors;

/*
| AGR-05: `unique:asset_groups,group_name` was global, so tenant B could not use "GR#101" once
| tenant A had it (the default name is GR#<unit_no>), and the error leaked that it exists.
| Names are now unique per company (the vehicle's company), soft-deleted groups included.
*/

function agrNewGroupPayload(\App\Models\Company $company, string $name): array
{
    return [
        'group_name' => $name,
        'driver_id' => DriverFactory::new()->forCompany($company)->create()->id,
        'vehicle_id' => VehicleFactory::new()->forCompany($company)->create()->id,
        'status' => 'active',
    ];
}

describe('AGR-05', function () {
    it('lets two tenants use the same group name', function () {
        AssetGroupFactory::new()->create(['group_name' => 'GR#101']);
        $tenant = AssetGroupActors::tenant();

        $this->actingAs($tenant)
            ->postJson(route('admin.asset-group.store'), agrNewGroupPayload(AssetGroupActors::company($tenant), 'GR#101'))
            ->assertOk();

        expect(AssetGroup::where('group_name', 'GR#101')->count())->toBe(2);
    });

    it('still rejects a duplicate name within one company', function (bool $trashed) {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        $existing = AssetGroupFactory::new()->forCompany($company)->create(['group_name' => 'GR#101']);
        if ($trashed) {
            $existing->delete();
        }

        $this->actingAs($tenant)
            ->postJson(route('admin.asset-group.store'), agrNewGroupPayload($company, 'GR#101'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('group_name');
    })->with(['active' => false, 'soft-deleted' => true]);

    it('rejects renaming a group to another group\'s name in the same company, but allows keeping its own', function () {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        AssetGroupFactory::new()->forCompany($company)->create(['group_name' => 'Taken']);
        $group = AssetGroupFactory::new()->forCompany($company)->create(['group_name' => 'Mine']);
        $payload = ['driver_id' => $group->driver_id, 'vehicle_id' => $group->vehicle_id, 'status' => 'active'];

        $this->actingAs($tenant)
            ->putJson(route('admin.asset-group.update', $group->id), $payload + ['group_name' => 'Taken'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('group_name');

        $this->putJson(route('admin.asset-group.update', $group->id), $payload + ['group_name' => 'Mine'])->assertOk();
    });

    it('checks a super-admin\'s new group against the vehicle\'s company', function () {
        $existing = AssetGroupFactory::new()->create(['group_name' => 'GR#101']);
        $company = $existing->vehicle->company;

        $this->actingAs(Actors::superAdmin())
            ->postJson(route('admin.asset-group.store'), agrNewGroupPayload($company, 'GR#101'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('group_name');
    });
});

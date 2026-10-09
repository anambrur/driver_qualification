<?php

use App\Models\AssetGroup;
use Database\Factories\AssetGroupFactory;
use Database\Factories\CompanyFactory;
use Database\Factories\DriverFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Modules\AssetGroups\AssetGroupActors;
use Tests\Support\Actors;

/*
| Follow-up: asset groups carry their own company_id (migration 2026_10_09_000400), backfilled
| from the vehicle. Tenant scoping and name uniqueness use it directly.
*/

describe('company_id', function () {
    it('stores the tenant\'s company on create', function () {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);

        $this->actingAs($tenant)->postJson(route('admin.asset-group.store'), [
            'group_name' => 'GR#1',
            'driver_id' => DriverFactory::new()->forCompany($company)->create()->id,
            'vehicle_id' => VehicleFactory::new()->forCompany($company)->create()->id,
            'status' => 'active',
            'company_id' => CompanyFactory::new()->create()->id,
        ])->assertOk();

        expect(AssetGroup::sole()->company_id)->toBe($company->id);
    });

    it('stores the vehicle\'s company when a super-admin creates a group', function () {
        $company = CompanyFactory::new()->create();

        $this->actingAs(Actors::superAdmin())->postJson(route('admin.asset-group.store'), [
            'group_name' => 'GR#1',
            'driver_id' => DriverFactory::new()->forCompany($company)->create()->id,
            'vehicle_id' => VehicleFactory::new()->forCompany($company)->create()->id,
            'status' => 'active',
        ])->assertOk();

        expect(AssetGroup::sole()->company_id)->toBe($company->id);
    });

    it('moves the group when a super-admin assigns another company\'s vehicle and driver', function () {
        $group = AssetGroupFactory::new()->create();
        $target = CompanyFactory::new()->create();

        $this->actingAs(Actors::superAdmin())->putJson(route('admin.asset-group.update', $group->id), [
            'group_name' => $group->group_name,
            'driver_id' => DriverFactory::new()->forCompany($target)->create()->id,
            'vehicle_id' => VehicleFactory::new()->forCompany($target)->create()->id,
            'status' => 'active',
        ])->assertOk();

        expect($group->fresh()->company_id)->toBe($target->id);
    });

    it('scopes tenants by the group\'s own company_id', function () {
        $tenant = AssetGroupActors::tenant();
        $own = AssetGroupFactory::new()->create(['company_id' => AssetGroupActors::company($tenant)->id]);
        AssetGroupFactory::new()->create();

        $this->actingAs($tenant)
            ->getJson(route('admin.asset-group.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.id', $own->id);
    });

    it('backfills company_id from the vehicle and makes it required', function () {
        $migration = require database_path('migrations/2026_10_09_000400_add_company_id_to_asset_groups_table.php');
        $group = AssetGroupFactory::new()->create();
        $vehicleCompany = $group->vehicle->company_id;

        $migration->down();
        expect(Schema::hasColumn('asset_groups', 'company_id'))->toBeFalse();

        $migration->up();

        expect(DB::table('asset_groups')->where('id', $group->id)->value('company_id'))->toBe($vehicleCompany)
            ->and(collect(Schema::getColumns('asset_groups'))->firstWhere('name', 'company_id')['nullable'])->toBeFalse()
            ->and(collect(Schema::getForeignKeys('asset_groups'))->contains(
                fn ($fk) => $fk['columns'] === ['company_id'] && $fk['foreign_table'] === 'companies'
            ))->toBeTrue();
    });
});

it('falls back to the vehicle\'s company when code creates a group without company_id', function () {
    $vehicle = VehicleFactory::new()->create();

    $group = AssetGroup::create([
        'group_name' => 'Seeded',
        'driver_id' => DriverFactory::new()->forCompany($vehicle->company)->create()->id,
        'vehicle_id' => $vehicle->id,
        'status' => 'active',
    ]);

    expect($group->fresh()->company_id)->toBe($vehicle->company_id);
});

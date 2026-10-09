<?php

use App\Models\AssetGroup;
use Database\Factories\AssetGroupFactory;
use Database\Factories\DriverFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Tests\Feature\Modules\AssetGroups\AssetGroupActors;
use Tests\Support\Actors;

/*
| AGR-01: asset groups had no tenant scoping. A group belongs to the company of its vehicle
| (asset_groups has no company_id; vehicle_id is a required FK). Cross-tenant access is 404.
| AGR-02: the index page and the dropdown endpoint listed every company's drivers/vehicles/trailers.
*/

const AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

function agrOtherTenantGroup(array $attributes = []): AssetGroup
{
    return AssetGroupFactory::new()->forCompany(AssetGroupActors::company(AssetGroupActors::tenant()))->create($attributes);
}

function agrPayload(AssetGroup $group, array $overrides = []): array
{
    return array_merge([
        'group_name' => $group->group_name,
        'driver_id' => $group->driver_id,
        'vehicle_id' => $group->vehicle_id,
        'status' => 'active',
    ], $overrides);
}

describe('AGR-01', function () {
    it('returns 404 when a tenant opens another tenant\'s group', function () {
        $foreign = agrOtherTenantGroup();

        $this->actingAs(AssetGroupActors::tenant())
            ->getJson(route('admin.asset-group.edit', $foreign->id))
            ->assertNotFound();
    });

    it('returns 404 and changes nothing when a tenant updates another tenant\'s group', function () {
        $foreign = agrOtherTenantGroup(['group_name' => 'Original']);

        $this->actingAs(AssetGroupActors::tenant())
            ->putJson(route('admin.asset-group.update', $foreign->id), agrPayload($foreign, ['group_name' => 'Hijacked']))
            ->assertNotFound();

        expect($foreign->fresh()->group_name)->toBe('Original');
    });

    it('returns 404 and keeps the group when a tenant deletes another tenant\'s group', function () {
        $foreign = agrOtherTenantGroup();

        $this->actingAs(AssetGroupActors::tenant())
            ->deleteJson(route('admin.asset-group.destroy', $foreign->id))
            ->assertNotFound();

        expect($foreign->fresh()->trashed())->toBeFalse();
    });

    it('returns 404 and keeps the group deleted when a tenant restores another tenant\'s group', function () {
        $foreign = agrOtherTenantGroup();
        $foreign->delete();

        $this->actingAs(AssetGroupActors::tenant())
            ->postJson(route('admin.asset-group.restore', $foreign->id))
            ->assertNotFound();

        expect($foreign->fresh()->trashed())->toBeTrue();
    });

    it('lists only the tenant\'s own groups', function () {
        $tenant = AssetGroupActors::tenant();
        $own = AssetGroupFactory::new()->forCompany(AssetGroupActors::company($tenant))->create(['group_name' => 'Own group']);
        agrOtherTenantGroup(['group_name' => 'Foreign group']);

        $response = $this->actingAs($tenant)->getJson(route('admin.asset-group.index'), AJAX)->assertOk();

        expect($response->json('recordsTotal'))->toBe(1)
            ->and($response->json('data.0.id'))->toBe($own->id);
    });

    it('still lets a tenant edit, update, delete and restore their own group', function () {
        $tenant = AssetGroupActors::tenant();
        $own = AssetGroupFactory::new()->forCompany(AssetGroupActors::company($tenant))->create();

        $this->actingAs($tenant)->getJson(route('admin.asset-group.edit', $own->id))->assertOk()->assertJsonPath('data.id', $own->id);
        $this->putJson(route('admin.asset-group.update', $own->id), agrPayload($own, ['group_name' => 'Renamed']))->assertOk();
        expect($own->fresh()->group_name)->toBe('Renamed');

        $this->deleteJson(route('admin.asset-group.destroy', $own->id))->assertOk();
        expect($own->fresh()->trashed())->toBeTrue();

        $this->postJson(route('admin.asset-group.restore', $own->id))->assertOk();
        expect($own->fresh()->trashed())->toBeFalse();
    });

    it('lets a super-admin see and edit every company\'s groups', function () {
        $foreign = agrOtherTenantGroup();
        agrOtherTenantGroup();

        $this->actingAs(Actors::superAdmin())
            ->getJson(route('admin.asset-group.index'), AJAX)
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2);

        $this->getJson(route('admin.asset-group.edit', $foreign->id))->assertOk();
    });

    it('gives the company role every asset-groups permission', function () {
        expect(Actors::companyOwner()->hasAllPermissions(AssetGroupActors::PERMISSIONS))->toBeTrue();
    });

    it('forbids users without asset-groups permissions', function (string $method, string $route, bool $needsId) {
        $group = agrOtherTenantGroup();
        $params = $needsId ? [$group->id] : [];

        $this->actingAs(Actors::plainUser())
            ->json($method, route($route, $params), [], AJAX)
            ->assertForbidden();
    })->with([
        'index' => ['GET', 'admin.asset-group.index', false],
        'store' => ['POST', 'admin.asset-group.store', false],
        'dropdown data' => ['GET', 'admin.asset-group.get-dropdown-data', false],
        'dropdown data (alias)' => ['GET', 'admin.asset-group.dropdown.data', false],
        'edit' => ['GET', 'admin.asset-group.edit', true],
        'update' => ['PUT', 'admin.asset-group.update', true],
        'destroy' => ['DELETE', 'admin.asset-group.destroy', true],
        'restore' => ['POST', 'admin.asset-group.restore', true],
    ]);
});

describe('AGR-02', function () {
    it('only offers the tenant\'s own drivers, vehicles and trailers on the index page', function () {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        $ownDriver = DriverFactory::new()->forCompany($company)->create();
        $ownVehicle = VehicleFactory::new()->forCompany($company)->create();
        $ownTrailer = TrailerFactory::new()->forCompany($company)->create();
        DriverFactory::new()->create();
        VehicleFactory::new()->create();
        TrailerFactory::new()->create();

        $this->actingAs($tenant)
            ->get(route('admin.asset-group.index'))
            ->assertOk()
            ->assertViewHas('drivers', fn ($drivers) => $drivers->pluck('id')->all() === [$ownDriver->id])
            ->assertViewHas('vehicles', fn ($vehicles) => $vehicles->pluck('id')->all() === [$ownVehicle->id])
            ->assertViewHas('trailers', fn ($trailers) => $trailers->pluck('id')->all() === [$ownTrailer->id]);
    });

    it('only returns the tenant\'s own vehicles and trailers from the dropdown endpoint', function (string $route) {
        $tenant = AssetGroupActors::tenant();
        $company = AssetGroupActors::company($tenant);
        $ownVehicle = VehicleFactory::new()->forCompany($company)->create();
        $ownTrailer = TrailerFactory::new()->forCompany($company)->create();
        VehicleFactory::new()->create();
        TrailerFactory::new()->create();

        $response = $this->actingAs($tenant)->getJson(route($route))->assertOk();

        expect(collect($response->json('vehicles'))->pluck('id')->all())->toBe([$ownVehicle->id])
            ->and(collect($response->json('trailers'))->pluck('id')->all())->toBe([$ownTrailer->id]);
    })->with(['admin.asset-group.get-dropdown-data', 'admin.asset-group.dropdown.data']);

    it('still shows a super-admin every company\'s active drivers', function () {
        DriverFactory::new()->create();
        DriverFactory::new()->create();
        DriverFactory::new()->status('inactive')->create();

        $this->actingAs(Actors::superAdmin())
            ->get(route('admin.asset-group.index'))
            ->assertOk()
            ->assertViewHas('drivers', fn ($drivers) => $drivers->count() === 2);
    });
});

<?php

use App\Models\AssetGroup;
use App\Models\Company;
use Database\Factories\AssetGroupFactory;
use Database\Factories\CompanyFactory;
use Database\Factories\DriverFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Tests\Feature\Modules\AssetGroups\AssetGroupActors;
use Tests\Support\Actors;

/*
| AGR-03: store/update validated driver_id/vehicle_id/trailer_id with bare `exists:`, so a tenant
| could attach another company's driver, vehicle or trailer. update() did not validate driver_id at all.
*/

function agrStorePayload(Company $company, array $overrides = []): array
{
    return array_merge([
        'group_name' => 'GR#'.fake()->unique()->numerify('#####'),
        'driver_id' => DriverFactory::new()->forCompany($company)->create()->id,
        'vehicle_id' => VehicleFactory::new()->forCompany($company)->create()->id,
        'trailer_id' => TrailerFactory::new()->forCompany($company)->create()->id,
        'status' => 'active',
    ], $overrides);
}

dataset('foreign ids', [
    'driver' => ['driver_id', fn () => DriverFactory::new()->create()->id],
    'vehicle' => ['vehicle_id', fn () => VehicleFactory::new()->create()->id],
    'trailer' => ['trailer_id', fn () => TrailerFactory::new()->create()->id],
]);

describe('AGR-03', function () {
    it('rejects another tenant\'s id on store', function (string $field, int $foreignId) {
        $tenant = AssetGroupActors::tenant();

        $this->actingAs($tenant)
            ->postJson(route('admin.asset-group.store'), agrStorePayload(AssetGroupActors::company($tenant), [$field => $foreignId]))
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);

        expect(AssetGroup::count())->toBe(0);
    })->with('foreign ids');

    it('rejects another tenant\'s id on update', function (string $field, int $foreignId) {
        $tenant = AssetGroupActors::tenant();
        $group = AssetGroupFactory::new()->forCompany(AssetGroupActors::company($tenant))->withTrailer()->create();
        $before = $group->only(['driver_id', 'vehicle_id', 'trailer_id']);

        $this->actingAs($tenant)
            ->putJson(route('admin.asset-group.update', $group->id), [
                'group_name' => $group->group_name,
                'driver_id' => $group->driver_id,
                'vehicle_id' => $group->vehicle_id,
                'trailer_id' => $group->trailer_id,
                'status' => 'active',
                $field => $foreignId,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);

        expect($group->fresh()->only(['driver_id', 'vehicle_id', 'trailer_id']))->toBe($before);
    })->with('foreign ids');

    it('accepts the tenant\'s own driver, vehicle and trailer', function () {
        $tenant = AssetGroupActors::tenant();
        $payload = agrStorePayload(AssetGroupActors::company($tenant));

        $this->actingAs($tenant)->postJson(route('admin.asset-group.store'), $payload)->assertOk();

        expect(AssetGroup::first()->only(['driver_id', 'vehicle_id', 'trailer_id']))
            ->toBe(\Illuminate\Support\Arr::only($payload, ['driver_id', 'vehicle_id', 'trailer_id']));
    });

    it('makes a super-admin pick the driver and trailer from the vehicle\'s company', function (string $field) {
        $vehicleCompany = CompanyFactory::new()->create();
        $payload = agrStorePayload($vehicleCompany, [
            $field => $field === 'driver_id'
                ? DriverFactory::new()->create()->id
                : TrailerFactory::new()->create()->id,
        ]);

        $this->actingAs(Actors::superAdmin())
            ->postJson(route('admin.asset-group.store'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);
    })->with(['driver_id', 'trailer_id']);

    it('lets a super-admin create a group for any company', function () {
        $payload = agrStorePayload(CompanyFactory::new()->create());

        $this->actingAs(Actors::superAdmin())->postJson(route('admin.asset-group.store'), $payload)->assertOk();

        expect(AssetGroup::count())->toBe(1);
    });
});

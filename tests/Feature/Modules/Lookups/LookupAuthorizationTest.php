<?php

use App\Models\DocumentType;
use App\Models\EquipmentType;
use App\Models\FuelType;
use App\Models\MaintenanceCategory;
use App\Models\Vehicle;
use App\Models\VehicleGroup;
use App\Models\VehicleType;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\Actors;

/*
| Lookups are global master data shared by every tenant (LKP-01, LKP-02, LKP-04). They are
| managed only in /platform by staff with platform.settings.manage (WP8); tenants can't reach
| those routes, and keep reading document types for their own opt-outs.
*/

dataset('lookups', [
    'vehicle type' => [VehicleType::class, 'platform.lookups.vehicle-types'],
    'vehicle group' => [VehicleGroup::class, 'platform.lookups.vehicle-groups'],
    'fuel type' => [FuelType::class, 'platform.lookups.fuel-types'],
    'equipment type' => [EquipmentType::class, 'platform.lookups.equipment-types'],
    'maintenance category' => [MaintenanceCategory::class, 'platform.lookups.maintenance-categories'],
    'document type' => [DocumentType::class, 'platform.lookups.document-types'],
]);

function lookupPayload(string $model, string $name): array
{
    return $model === DocumentType::class
        ? ['name' => $name, 'module' => 'driver', 'status' => 1]
        : ['name' => $name];
}

function createLookup(string $model, string $name = 'Shared Lookup'): Model
{
    return $model::query()->create(lookupPayload($model, $name));
}

describe('LKP-01: tenants cannot delete global lookups', function () {
    it('forbids a tenant from deleting a lookup', function (string $model, string $route) {
        $lookup = createLookup($model);

        $this->actingAs(Actors::companyOwner())
            ->deleteJson(route("$route.destroy", $lookup->id))
            ->assertUnauthorized();

        expect($model::query()->whereKey($lookup->id)->exists())->toBeTrue();
    })->with('lookups');

    it('forbids platform staff without platform.settings.manage', function (string $model, string $route) {
        $lookup = createLookup($model);

        $this->actingAs(Actors::platformSupport(), 'platform')
            ->deleteJson(route("$route.destroy", $lookup->id))
            ->assertForbidden();

        expect($model::query()->whereKey($lookup->id)->exists())->toBeTrue();
    })->with('lookups');

    it("does not wipe another tenant's vehicles through the fuel type cascade", function () {
        $diesel = FuelType::query()->create(['name' => 'Diesel']);
        $tenantB = Actors::companyOf(Actors::companyOwner());
        $vehicle = VehicleFactory::new()->forCompany($tenantB)->create(['fuel_type_id' => $diesel->id]);

        $this->actingAs(Actors::companyOwner())
            ->deleteJson(route('platform.lookups.fuel-types.destroy', $diesel->id))
            ->assertUnauthorized();

        expect(Vehicle::withoutTenancy()->withTrashed()->whereKey($vehicle->id)->exists())->toBeTrue();
    });
});

describe('LKP-02: tenants cannot create or rename global lookups', function () {
    it('forbids a tenant from creating a lookup', function (string $model, string $route) {
        $this->actingAs(Actors::companyOwner())
            ->postJson(route("$route.store"), lookupPayload($model, 'Tenant Made'))
            ->assertUnauthorized();

        expect($model::query()->where('name', 'Tenant Made')->exists())->toBeFalse();
    })->with('lookups');

    it('forbids a tenant from renaming a lookup', function (string $model, string $route) {
        $lookup = createLookup($model, 'Original');

        $this->actingAs(Actors::companyOwner())
            ->putJson(route("$route.update", $lookup->id), lookupPayload($model, 'Renamed'))
            ->assertUnauthorized();

        expect($lookup->fresh()->name)->toBe('Original');
    })->with('lookups');

    it('lists every lookup for platform staff', function (string $model, string $route) {
        createLookup($model);

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->getJson(route("$route.index"), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1);
    })->with('lookups');

    it('still lets a tenant view the document types it can switch off', function () {
        createLookup(DocumentType::class);

        $this->actingAs(Actors::companyOwner())
            ->getJson(route('admin.settings.document-types.index'), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1);
    });
});

describe('LKP-04: tenants cannot change document types', function () {
    it('forbids a tenant from toggling a document type', function () {
        $type = DocumentType::factory()->create(['status' => true]);

        $this->actingAs(Actors::companyOwner())
            ->postJson(route('platform.lookups.document-types.toggle-status', $type->id))
            ->assertUnauthorized();

        expect($type->fresh()->status)->toBeTrue();
    });
});

describe('the platform keeps full control', function () {
    it('lets platform staff create, rename and delete an unused lookup', function (string $model, string $route) {
        $admin = Actors::platformOwner();

        $this->actingAs($admin, 'platform')
            ->postJson(route("$route.store"), lookupPayload($model, 'Admin Made'))
            ->assertOk();

        $lookup = $model::query()->where('name', 'Admin Made')->firstOrFail();

        // Vehicle types answer an update with a redirect, the others with JSON.
        $update = $this->actingAs($admin, 'platform')
            ->putJson(route("$route.update", $lookup->id), lookupPayload($model, 'Admin Renamed'));

        expect($update->status())->toBeLessThan(400)
            ->and($lookup->fresh()->name)->toBe('Admin Renamed');

        $this->actingAs($admin, 'platform')
            ->deleteJson(route("$route.destroy", $lookup->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        expect($model::query()->whereKey($lookup->id)->exists())->toBeFalse();
    })->with('lookups');
});

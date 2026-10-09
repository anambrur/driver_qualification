<?php

use App\Models\DocumentType;
use App\Models\EquipmentType;
use App\Models\FuelType;
use App\Models\MaintenanceCategory;
use App\Models\Vehicle;
use App\Models\VehicleGroup;
use App\Models\VehicleType;
use Database\Factories\VehicleFactory;
use Tests\Support\Actors;

/*
| Lookups are global master data shared by every tenant (LKP-01, LKP-02, LKP-04).
| Only super-admins may create, rename, delete or toggle them; tenants keep read access.
*/

dataset('lookups', [
    'vehicle type' => [VehicleType::class, 'admin.vehicle.type'],
    'vehicle group' => [VehicleGroup::class, 'admin.vehicle.group'],
    'fuel type' => [FuelType::class, 'admin.fuel.type'],
    'equipment type' => [EquipmentType::class, 'admin.equipment.type'],
    'maintenance category' => [MaintenanceCategory::class, 'admin.maintenance.category'],
    'document type' => [DocumentType::class, 'admin.settings.document-types'],
]);

function lookupPayload(string $model, string $name): array
{
    return $model === DocumentType::class
        ? ['name' => $name, 'module' => 'driver', 'status' => 1]
        : ['name' => $name];
}

function createLookup(string $model, string $name = 'Shared Lookup'): \Illuminate\Database\Eloquent\Model
{
    return $model::query()->create(lookupPayload($model, $name));
}

describe('LKP-01: tenants cannot delete global lookups', function () {
    it('forbids a tenant from deleting a lookup', function (string $model, string $route) {
        $lookup = createLookup($model);

        $this->actingAs(Actors::companyOwner())
            ->deleteJson(route("$route.destroy", $lookup->id))
            ->assertForbidden();

        expect($model::query()->whereKey($lookup->id)->exists())->toBeTrue();
    })->with('lookups');

    it("does not wipe another tenant's vehicles through the fuel type cascade", function () {
        $diesel = FuelType::query()->create(['name' => 'Diesel']);
        $tenantB = Actors::companyOf(Actors::companyOwner());
        $vehicle = VehicleFactory::new()->forCompany($tenantB)->create(['fuel_type_id' => $diesel->id]);

        $this->actingAs(Actors::companyOwner())
            ->deleteJson(route('admin.fuel.type.destroy', $diesel->id))
            ->assertForbidden();

        expect(Vehicle::withTrashed()->whereKey($vehicle->id)->exists())->toBeTrue();
    });
});

describe('LKP-02: tenants cannot create or rename global lookups', function () {
    it('forbids a tenant from creating a lookup', function (string $model, string $route) {
        $this->actingAs(Actors::companyOwner())
            ->postJson(route("$route.store"), lookupPayload($model, 'Tenant Made'))
            ->assertForbidden();

        expect($model::query()->where('name', 'Tenant Made')->exists())->toBeFalse();
    })->with('lookups');

    it('forbids a tenant from renaming a lookup', function (string $model, string $route) {
        $lookup = createLookup($model, 'Original');

        $this->actingAs(Actors::companyOwner())
            ->putJson(route("$route.update", $lookup->id), lookupPayload($model, 'Renamed'))
            ->assertForbidden();

        expect($lookup->fresh()->name)->toBe('Original');
    })->with('lookups');

    it('still lets a tenant view the lookup list', function (string $model, string $route) {
        createLookup($model);

        $this->actingAs(Actors::companyOwner())
            ->getJson(route("$route.index"), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1);
    })->with('lookups');
});

describe('LKP-04: tenants cannot change document types', function () {
    it('forbids a tenant from toggling a document type', function () {
        $type = DocumentType::factory()->create(['status' => true]);

        $this->actingAs(Actors::companyOwner())
            ->postJson(route('admin.settings.document-types.toggle-status', $type->id))
            ->assertForbidden();

        expect($type->fresh()->status)->toBeTrue();
    });
});

describe('super-admin keeps full control', function () {
    it('lets a super-admin create, rename and delete an unused lookup', function (string $model, string $route) {
        $admin = Actors::superAdmin();

        $this->actingAs($admin)
            ->postJson(route("$route.store"), lookupPayload($model, 'Admin Made'))
            ->assertOk();

        $lookup = $model::query()->where('name', 'Admin Made')->firstOrFail();

        // Vehicle types answer an update with a redirect, the others with JSON.
        $update = $this->actingAs($admin)
            ->putJson(route("$route.update", $lookup->id), lookupPayload($model, 'Admin Renamed'));

        expect($update->status())->toBeLessThan(400)
            ->and($lookup->fresh()->name)->toBe('Admin Renamed');

        $this->actingAs($admin)
            ->deleteJson(route("$route.destroy", $lookup->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        expect($model::query()->whereKey($lookup->id)->exists())->toBeFalse();
    })->with('lookups');
});

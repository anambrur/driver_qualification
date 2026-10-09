<?php

use App\Models\DocumentType;
use App\Models\EquipmentType;
use App\Models\FuelType;
use App\Models\MaintenanceCategory;
use App\Models\Trailer;
use App\Models\Vehicle;
use App\Models\VehicleGroup;
use App\Models\VehicleType;
use Database\Factories\DriverFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\DB;
use Tests\Support\Actors;

/*
| Lookup FKs are onDelete('cascade'), so deleting a lookup that is in use hard-deletes
| every tenant's rows that reference it (bypassing SoftDeletes). Even a super-admin
| must not be able to do that by accident: in-use lookups are refused.
*/

describe('LKP-01: deleting a lookup used by vehicles', function () {
    it('refuses and keeps every tenant vehicle', function (string $model, string $route, string $column) {
        $lookup = $model::query()->create(['name' => 'In Use']);
        $vehicle = VehicleFactory::new()
            ->forCompany(Actors::companyOf(Actors::companyOwner()))
            ->create([$column => $lookup->id]);

        $this->actingAs(Actors::superAdmin())
            ->deleteJson(route("$route.destroy", $lookup->id))
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        expect($model::query()->whereKey($lookup->id)->exists())->toBeTrue()
            ->and(Vehicle::query()->whereKey($vehicle->id)->exists())->toBeTrue();
    })->with([
        'fuel type' => [FuelType::class, 'admin.fuel.type', 'fuel_type_id'],
        'vehicle type' => [VehicleType::class, 'admin.vehicle.type', 'vehicle_type_id'],
        'vehicle group' => [VehicleGroup::class, 'admin.vehicle.group', 'vehicle_group_id'],
    ]);

    it('counts soft-deleted vehicles too, since the cascade would hard-delete them', function () {
        $diesel = FuelType::query()->create(['name' => 'Diesel']);
        $vehicle = VehicleFactory::new()->create(['fuel_type_id' => $diesel->id]);
        $vehicle->delete();

        $this->actingAs(Actors::superAdmin())
            ->deleteJson(route('admin.fuel.type.destroy', $diesel->id))
            ->assertStatus(400);

        expect(Vehicle::withTrashed()->whereKey($vehicle->id)->exists())->toBeTrue();
    });

    it('refuses to delete a vehicle group used only by a trailer', function () {
        $group = VehicleGroup::query()->create(['name' => 'Reefers']);
        $trailer = TrailerFactory::new()->create(['vehicle_group_id' => $group->id]);

        $this->actingAs(Actors::superAdmin())
            ->deleteJson(route('admin.vehicle.group.destroy', $group->id))
            ->assertStatus(400);

        expect(Trailer::query()->whereKey($trailer->id)->exists())->toBeTrue();
    });
});

describe('LKP-05: deleting an equipment type used by trailers', function () {
    it('refuses and keeps the trailer', function () {
        $type = EquipmentType::query()->create(['name' => 'Flatbed']);
        $trailer = TrailerFactory::new()->create(['equipment_types_id' => $type->id]);

        $this->actingAs(Actors::superAdmin())
            ->deleteJson(route('admin.equipment.type.destroy', $type->id))
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        expect(EquipmentType::query()->whereKey($type->id)->exists())->toBeTrue()
            ->and(Trailer::query()->whereKey($trailer->id)->exists())->toBeTrue();
    });
});

describe('LKP-03: deleting a maintenance category', function () {
    it('deletes an unused category instead of failing with a 500', function () {
        $category = MaintenanceCategory::query()->create(['name' => 'Brakes']);

        $this->actingAs(Actors::superAdmin())
            ->deleteJson(route('admin.maintenance.category.destroy', $category->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        expect(MaintenanceCategory::query()->whereKey($category->id)->exists())->toBeFalse();
    });

    it('refuses to delete a category linked to a service log', function () {
        $category = MaintenanceCategory::query()->create(['name' => 'Brakes']);
        $vehicle = VehicleFactory::new()->create();
        $logId = DB::table('service_logs')->insertGetId([
            'company_id' => $vehicle->company_id,
            'vehicle_id' => $vehicle->id,
            'service_date' => now()->toDateString(),
        ]);
        DB::table('service_log_category')->insert(['service_log_id' => $logId, 'maintenance_category_id' => $category->id]);

        $this->actingAs(Actors::superAdmin())
            ->deleteJson(route('admin.maintenance.category.destroy', $category->id))
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        expect(DB::table('service_log_category')->where('maintenance_category_id', $category->id)->exists())->toBeTrue();
    });

    it('refuses to delete a category used by a maintenance schedule', function () {
        $category = MaintenanceCategory::query()->create(['name' => 'Oil Change']);
        $vehicle = VehicleFactory::new()->create();
        $scheduleId = DB::table('maintenance_schedules')->insertGetId([
            'company_id' => $vehicle->company_id,
            'vehicle_id' => $vehicle->id,
            'maintenance_category_id' => $category->id,
        ]);

        $this->actingAs(Actors::superAdmin())
            ->deleteJson(route('admin.maintenance.category.destroy', $category->id))
            ->assertStatus(400);

        expect(DB::table('maintenance_schedules')->where('id', $scheduleId)->exists())->toBeTrue();
    });
});

describe('LKP-04: deleting a document type that tenants have uploaded files for', function () {
    it('refuses and keeps the uploaded documents', function (string $table, Closure $owner) {
        $type = DocumentType::factory()->create();
        [$column, $ownerId] = $owner();
        $docId = DB::table($table)->insertGetId([
            $column => $ownerId,
            'document_type_id' => $type->id,
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        $this->actingAs(Actors::superAdmin())
            ->deleteJson(route('admin.settings.document-types.destroy', $type->id))
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        expect(DocumentType::query()->whereKey($type->id)->exists())->toBeTrue()
            ->and(DB::table($table)->where('id', $docId)->exists())->toBeTrue();
    })->with([
        'driver compliance document' => ['driver_compliance_documents', fn () => ['driver_id', DriverFactory::new()->create()->id]],
        'vehicle document' => ['vehicle_documents', fn () => ['vehicle_id', VehicleFactory::new()->create()->id]],
        'trailer document' => ['trailer_documents', fn () => ['trailer_id', TrailerFactory::new()->create()->id]],
    ]);
});

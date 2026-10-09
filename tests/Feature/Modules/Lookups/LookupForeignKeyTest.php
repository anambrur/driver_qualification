<?php

use App\Models\DocumentType;
use App\Models\EquipmentType;
use App\Models\FuelType;
use App\Models\MaintenanceCategory;
use App\Models\VehicleGroup;
use App\Models\VehicleType;
use Database\Factories\DriverFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
| LKP-01 follow-up: the database itself refuses to delete a lookup that tenant rows still
| reference (ON DELETE RESTRICT), so no code path, not even raw SQL, can cascade-wipe
| vehicles, trailers, schedules or uploaded documents.
*/

dataset('lookup links', [
    'vehicles.vehicle_type_id' => ['vehicle_types', 'vehicles', fn () => VehicleFactory::new()->create([
        'vehicle_type_id' => VehicleType::query()->create(['name' => 'Tractor'])->id,
    ])->vehicle_type_id],
    'vehicles.vehicle_group_id' => ['vehicle_groups', 'vehicles', fn () => VehicleFactory::new()->create([
        'vehicle_group_id' => VehicleGroup::query()->create(['name' => 'Fleet'])->id,
    ])->vehicle_group_id],
    'vehicles.fuel_type_id' => ['fuel_types', 'vehicles', fn () => VehicleFactory::new()->create([
        'fuel_type_id' => FuelType::query()->create(['name' => 'Diesel'])->id,
    ])->fuel_type_id],
    'trailers.equipment_types_id' => ['equipment_types', 'trailers', fn () => TrailerFactory::new()->create([
        'equipment_types_id' => EquipmentType::query()->create(['name' => 'Flatbed'])->id,
    ])->equipment_types_id],
    'trailers.vehicle_group_id' => ['vehicle_groups', 'trailers', fn () => TrailerFactory::new()->create([
        'vehicle_group_id' => VehicleGroup::query()->create(['name' => 'Reefers'])->id,
    ])->vehicle_group_id],
    'maintenance_schedules.maintenance_category_id' => ['maintenance_categories', 'maintenance_schedules', function () {
        $vehicle = VehicleFactory::new()->create();
        $categoryId = MaintenanceCategory::query()->create(['name' => 'Brakes'])->id;
        DB::table('maintenance_schedules')->insert([
            'company_id' => $vehicle->company_id,
            'vehicle_id' => $vehicle->id,
            'maintenance_category_id' => $categoryId,
        ]);

        return $categoryId;
    }],
    'service_log_category.maintenance_category_id' => ['maintenance_categories', 'service_log_category', function () {
        $vehicle = VehicleFactory::new()->create();
        $categoryId = MaintenanceCategory::query()->create(['name' => 'Tires'])->id;
        $logId = DB::table('service_logs')->insertGetId([
            'company_id' => $vehicle->company_id,
            'vehicle_id' => $vehicle->id,
            'service_date' => now()->toDateString(),
        ]);
        DB::table('service_log_category')->insert(['service_log_id' => $logId, 'maintenance_category_id' => $categoryId]);

        return $categoryId;
    }],
    'driver_compliance_documents.document_type_id' => ['document_types', 'driver_compliance_documents', function () {
        $typeId = DocumentType::factory()->create()->id;
        DB::table('driver_compliance_documents')->insert(['driver_id' => DriverFactory::new()->create()->id, 'document_type_id' => $typeId]);

        return $typeId;
    }],
    'vehicle_documents.document_type_id' => ['document_types', 'vehicle_documents', function () {
        $typeId = DocumentType::factory()->module('vehicle')->create()->id;
        DB::table('vehicle_documents')->insert([
            'vehicle_id' => VehicleFactory::new()->create()->id,
            'document_type_id' => $typeId,
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        return $typeId;
    }],
    'trailer_documents.document_type_id' => ['document_types', 'trailer_documents', function () {
        $typeId = DocumentType::factory()->module('trailer')->create()->id;
        DB::table('trailer_documents')->insert([
            'trailer_id' => TrailerFactory::new()->create()->id,
            'document_type_id' => $typeId,
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        return $typeId;
    }],
]);

it('refuses at the database level to delete a lookup that is still referenced', function (string $lookupTable, string $childTable, Closure $seed) {
    $lookupId = $seed();
    $childRows = DB::table($childTable)->count();

    expect(fn () => DB::table($lookupTable)->where('id', $lookupId)->delete())
        ->toThrow(QueryException::class);

    expect(DB::table($lookupTable)->where('id', $lookupId)->exists())->toBeTrue()
        ->and(DB::table($childTable)->count())->toBe($childRows);
})->with('lookup links');

it('still deletes a lookup nothing references', function () {
    $fuel = FuelType::query()->create(['name' => 'Hydrogen']);

    DB::table('fuel_types')->where('id', $fuel->id)->delete();

    expect(FuelType::query()->whereKey($fuel->id)->exists())->toBeFalse();
});

<?php

use App\Models\MaintenanceSchedule;
use App\Models\ServiceDocument;
use App\Models\ServiceLog;
use App\Tenancy\TenantContext;
use Database\Factories\MaintenanceCategoryFactory;
use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\ServiceLogFactory;
use Database\Factories\StoredFileFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;

/*
| WP5 group 3: service logs, their documents and maintenance schedules carry the tenant scope
| (03 §4.2). Another company's record is a 404 by URL; another company's vehicle is refused
| in the request body.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->companyA = Actors::company();
    $this->companyB = Actors::company();
    $this->vehicleA = VehicleFactory::new()->forCompany($this->companyA)->create();
    $this->vehicleB = VehicleFactory::new()->forCompany($this->companyB)->create();
    $this->logA = ServiceLogFactory::new()->forVehicle($this->vehicleA)->create();
    $this->logB = ServiceLogFactory::new()->forVehicle($this->vehicleB)->create();
    $this->scheduleA = MaintenanceScheduleFactory::new()->forVehicle($this->vehicleA)->create();
    $this->scheduleB = MaintenanceScheduleFactory::new()->forVehicle($this->vehicleB)->create();
    $this->documentA = app(TenantContext::class)->run($this->companyA->id, fn () => ServiceDocument::create([
        'service_log_id' => $this->logA->id,
        'file_id' => StoredFileFactory::new()->forCompany($this->companyA)->create()->id,
    ]));
    $this->ownerB = $this->companyB->owner;
});

it('shows each company only its own logs, documents and schedules', function () {
    $seen = app(TenantContext::class)->run($this->companyB->id, fn () => [
        ServiceLog::pluck('id')->all(),
        ServiceDocument::count(),
        MaintenanceSchedule::pluck('id')->all(),
    ]);

    expect($seen)->toBe([[$this->logB->id], 0, [$this->scheduleB->id]]);
});

it('returns 404 for another company\'s record by URL', function (string $method, string $route, string $record) {
    $this->actingAs($this->ownerB)
        ->json($method, route($route, $this->{$record}->id))
        ->assertNotFound();
})->with([
    'log show' => ['GET', 'admin.service-log.show', 'logA'],
    'log edit' => ['GET', 'admin.service-log.edit', 'logA'],
    'log update' => ['PUT', 'admin.service-log.update', 'logA'],
    'log destroy' => ['DELETE', 'admin.service-log.destroy', 'logA'],
    'log vehicle details' => ['GET', 'admin.service-log.get-vehicle-details', 'vehicleA'],
    'document download' => ['GET', 'admin.service-log.download-document', 'documentA'],
    'document delete' => ['DELETE', 'admin.service-log.delete-document', 'documentA'],
    'schedule show' => ['GET', 'admin.maintenance-schedule.show', 'scheduleA'],
    'schedule edit' => ['GET', 'admin.maintenance-schedule.edit', 'scheduleA'],
    'schedule update' => ['PUT', 'admin.maintenance-schedule.update', 'scheduleA'],
    'schedule destroy' => ['DELETE', 'admin.maintenance-schedule.destroy', 'scheduleA'],
    'schedule mark completed' => ['POST', 'admin.maintenance-schedule.mark-completed', 'scheduleA'],
    'schedule vehicle details' => ['GET', 'admin.maintenance-schedule.get-vehicle-details', 'vehicleA'],
]);

it('keeps the records when another company deletes them', function () {
    $this->actingAs($this->ownerB)->deleteJson(route('admin.service-log.destroy', $this->logA->id))->assertNotFound();
    $this->actingAs($this->ownerB)->deleteJson(route('admin.maintenance-schedule.destroy', $this->scheduleA->id))->assertNotFound();
    $this->actingAs($this->ownerB)->deleteJson(route('admin.service-log.delete-document', $this->documentA->id))->assertNotFound();

    expect(ServiceLog::withoutTenancy()->find($this->logA->id))->not->toBeNull()
        ->and(MaintenanceSchedule::withoutTenancy()->find($this->scheduleA->id))->not->toBeNull()
        ->and(ServiceDocument::withoutTenancy()->find($this->documentA->id))->not->toBeNull();
});

it('refuses another company\'s vehicle in a new log or schedule', function (string $route, array $payload) {
    $this->actingAs($this->ownerB)
        ->postJson(route($route), ['vehicle_id' => $this->vehicleA->id] + $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('vehicle_id', 'errors');

    expect(ServiceLog::withoutTenancy()->count())->toBe(2)
        ->and(MaintenanceSchedule::withoutTenancy()->count())->toBe(2);
})->with([
    'service log' => fn () => ['admin.service-log.store', [
        'service_date' => now()->toDateString(),
        'maintenance_categories' => [MaintenanceCategoryFactory::new()->create()->id],
        'odometer_at_service' => 1000, 'current_odometer' => 1000, 'total_cost' => 50, 'status' => 'completed',
    ]],
    'schedule' => fn () => ['admin.maintenance-schedule.store', [
        'maintenance_category_id' => MaintenanceCategoryFactory::new()->create()->id,
        'schedule_type' => 'date', 'interval_days' => 30, 'status' => 'active',
    ]],
]);

<?php

use App\Models\MaintenanceSchedule;
use App\Models\ServiceDocument;
use App\Models\ServiceLog;
use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\ServiceLogFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\Maintenance\Maintenance;
use Tests\Support\Actors;

/*
| Product decisions of 2026-10-11 (docs/testing/PRODUCT_DECISIONS.md):
| PO-04  "Mark as completed" records the service and keeps a recurring schedule in its status.
| PO-05  engine hours are a column on vehicles, raised by service logs like the odometer.
| PO-06  mileage and engine-hour schedules need a vehicle.
| PO-07  a super-admin picks the company of an all-vehicles schedule.
| PO-22  deleting a service log deletes its document rows; a log corrected downwards can lower
|        the vehicle's odometer / engine hours when it was the highest one.
*/

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('PO-04: completing a recurring schedule', function () {
    it('keeps the schedule active and rolls the next due date forward', function () {
        Carbon::setTestNow('2026-10-10 12:00:00');
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create(['interval_days' => 30, 'next_due_date' => '2026-10-01', 'status' => 'active']);

        $this->actingAs($owner)->postJson(route('admin.maintenance-schedule.mark-completed', $schedule->id))->assertOk();

        expect($schedule->fresh())
            ->status->toBe('active')
            ->last_due_date->toDateString()->toBe('2026-10-10')
            ->next_due_date->toDateString()->toBe('2026-11-09')
            ->and(MaintenanceSchedule::active()->whereKey($schedule->id)->exists())->toBeTrue();
    });

    it('can still be retired by editing its status to completed', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create(['interval_days' => 30]);

        $this->actingAs($owner)->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload($vehicle, [
            'interval_days' => 30,
            'status' => 'completed',
        ]))->assertOk();

        expect($schedule->fresh()->status)->toBe('completed');
    });
});

describe('PO-05: vehicle engine hours', function () {
    it('is raised by a service log and never lowered by an older one', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle(['engine_hours' => 100]);

        $this->actingAs($owner)->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['engine_hours_at_service' => 500, 'current_engine_hours' => 500]))->assertOk();
        expect($vehicle->fresh()->engine_hours)->toBe(500);

        $this->actingAs($owner)->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['engine_hours_at_service' => 300, 'current_engine_hours' => 300]))->assertOk();
        expect($vehicle->fresh()->engine_hours)->toBe(500);
    });

    it('drives engine-hour schedules', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle(['engine_hours' => 900]);

        $this->actingAs($owner)->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload($vehicle, [
            'schedule_type' => 'engine_hours',
            'interval_hours' => 250,
        ]))->assertOk();

        $schedule = MaintenanceSchedule::sole();
        expect($schedule->next_due_hours)->toBe(1150)->and($schedule->isDue())->toBeFalse();

        $vehicle->update(['engine_hours' => 1150]);
        expect($schedule->fresh()->isDue())->toBeTrue();
    });

    it('backfills the column from the highest logged value', function () {
        [, $vehicle] = Maintenance::tenantWithVehicle();
        [, $unlogged] = Maintenance::tenantWithVehicle();
        ServiceLogFactory::new()->forVehicle($vehicle)->create(['current_engine_hours' => 1200]);
        ServiceLogFactory::new()->forVehicle($vehicle)->create(['current_engine_hours' => 800]);
        $migration = require database_path('migrations/2026_10_11_000100_add_engine_hours_to_vehicles.php');

        $migration->down();
        $migration->up();
        $migration->up(); // safe to re-run

        expect(DB::table('vehicles')->where('id', $vehicle->id)->value('engine_hours'))->toBe(1200)
            ->and(DB::table('vehicles')->where('id', $unlogged->id)->value('engine_hours'))->toBeNull();
    });
});

describe('PO-06: mileage and engine-hour schedules need a vehicle', function () {
    it('refuses one for all vehicles', function (string $type, string $intervalField) {
        [$owner] = Maintenance::tenantWithVehicle();

        $this->actingAs($owner)->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload(null, [
            'schedule_type' => $type,
            $intervalField => 5000,
        ]))->assertStatus(422)->assertJsonValidationErrors('vehicle_id', 'errors');

        expect(MaintenanceSchedule::count())->toBe(0);
    })->with([
        'mileage' => ['mileage', 'interval_miles'],
        'engine hours' => ['engine_hours', 'interval_hours'],
    ]);

    it('refuses an update that makes one apply to all vehicles', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->mileage(5000)->create();

        $this->actingAs($owner)->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload(null, [
            'schedule_type' => 'mileage',
            'interval_miles' => 5000,
        ]))->assertStatus(422);

        expect($schedule->fresh()->vehicle_id)->toBe($vehicle->id);
    });

    it('still allows a date schedule for all vehicles', function () {
        [$owner] = Maintenance::tenantWithVehicle();

        $this->actingAs($owner)->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload(null))->assertOk();

        expect(MaintenanceSchedule::sole()->vehicle_id)->toBeNull();
    });
});

describe('PO-07: a super-admin\'s schedule for all vehicles', function () {
    it('requires a company', function () {
        $this->actingAs(Actors::superAdmin())
            ->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload(null))
            ->assertStatus(422)->assertJsonValidationErrors('company_id', 'errors');

        expect(MaintenanceSchedule::count())->toBe(0);
    });

    it('is filed under the company picked', function () {
        [$owner] = Maintenance::tenantWithVehicle();
        $company = Actors::companyOf($owner);

        $this->actingAs(Actors::superAdmin())
            ->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload(null, ['company_id' => $company->id]))
            ->assertOk();

        expect(MaintenanceSchedule::sole()->company_id)->toBe($company->id);
    });

    it('takes the vehicle\'s company when there is a vehicle, whatever company is posted', function () {
        [, $vehicle] = Maintenance::tenantWithVehicle();
        [$other] = Maintenance::tenantWithVehicle();

        $this->actingAs(Actors::superAdmin())
            ->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload($vehicle, ['company_id' => Actors::companyOf($other)->id]))
            ->assertOk();

        expect(MaintenanceSchedule::sole()->company_id)->toBe($vehicle->company_id);
    });

    it('can be moved to another company on update', function () {
        [$owner] = Maintenance::tenantWithVehicle();
        [$other] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->create(['company_id' => Actors::companyOf($owner)->id, 'vehicle_id' => null]);

        $this->actingAs(Actors::superAdmin())
            ->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload(null, ['company_id' => Actors::companyOf($other)->id]))
            ->assertOk();

        expect($schedule->fresh()->company_id)->toBe(Actors::companyOf($other)->id);
    });

    it('ignores a company_id posted by a tenant', function () {
        [$owner] = Maintenance::tenantWithVehicle();
        [$other] = Maintenance::tenantWithVehicle();

        $this->actingAs($owner)
            ->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload(null, ['company_id' => Actors::companyOf($other)->id]))
            ->assertOk();

        expect(MaintenanceSchedule::sole()->company_id)->toBe(Actors::companyOf($owner)->id);
    });

    it('shows the company select to super-admins only', function () {
        $this->actingAs(Actors::superAdmin())->get(route('admin.maintenance-schedule.index'))
            ->assertOk()->assertSee('id="company_id"', false);

        [$owner] = Maintenance::tenantWithVehicle();
        $this->actingAs($owner)->get(route('admin.maintenance-schedule.index'))
            ->assertOk()->assertDontSee('id="company_id"', false);
    });
});

describe('PO-22: service log follow-ups', function () {
    it('deletes the document rows with the log, and their files', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $this->actingAs($owner)
            ->post(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['documents' => [Maintenance::pdf()]]), ['Accept' => 'application/json'])
            ->assertOk();
        $document = ServiceDocument::sole();

        $this->actingAs($owner)->deleteJson(route('admin.service-log.destroy', $document->service_log_id))->assertOk();

        expect(ServiceDocument::count())->toBe(0);
        Storage::disk('local')->assertMissing($document->file_path);
    });

    it('lowers the odometer when the highest log is corrected downwards', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle(['odometer' => 1000]);
        $this->actingAs($owner)->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['odometer_at_service' => 40000, 'current_odometer' => 40000]))->assertOk();
        $this->actingAs($owner)->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['odometer_at_service' => 150000, 'current_odometer' => 150000]))->assertOk();
        expect($vehicle->fresh()->odometer)->toBe(150000);

        $typo = ServiceLog::where('current_odometer', 150000)->sole();
        $this->actingAs($owner)->putJson(route('admin.service-log.update', $typo->id), Maintenance::serviceLogPayload($vehicle, [
            'maintenance_categories' => $typo->maintenanceCategories->pluck('id')->all() ?: [Maintenance::category()->id],
            'odometer_at_service' => 15000,
            'current_odometer' => 15000,
        ]))->assertOk();

        // the highest value logged now
        expect($vehicle->fresh()->odometer)->toBe(40000);
    });

    it('keeps the odometer when a log that was not the highest is corrected downwards', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle(['odometer' => 1000]);
        $this->actingAs($owner)->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['odometer_at_service' => 30000, 'current_odometer' => 30000]))->assertOk();
        $this->actingAs($owner)->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['odometer_at_service' => 50000, 'current_odometer' => 50000]))->assertOk();

        $older = ServiceLog::where('current_odometer', 30000)->sole();
        $this->actingAs($owner)->putJson(route('admin.service-log.update', $older->id), Maintenance::serviceLogPayload($vehicle, [
            'odometer_at_service' => 20000,
            'current_odometer' => 20000,
        ]))->assertOk();

        expect($vehicle->fresh()->odometer)->toBe(50000);
    });

    it('lowers engine hours the same way', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $this->actingAs($owner)->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['engine_hours_at_service' => 9000, 'current_engine_hours' => 9000]))->assertOk();

        $log = ServiceLog::sole();
        $this->actingAs($owner)->putJson(route('admin.service-log.update', $log->id), Maintenance::serviceLogPayload($vehicle, [
            'engine_hours_at_service' => 900,
            'current_engine_hours' => 900,
        ]))->assertOk();

        expect($vehicle->fresh()->engine_hours)->toBe(900);
    });
});

<?php

use App\Models\MaintenanceSchedule;
use App\Models\ServiceLog;
use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\ServiceLogFactory;
use Tests\Feature\Modules\Maintenance\Maintenance;
use Tests\Support\Actors;

/*
| MNT-01: store/update validated vehicle_id with a bare `exists:vehicles,id`, so a tenant could
| file a service log or schedule against another company's vehicle (and push that vehicle's
| odometer up through updateVehicleMetrics()).
|
| MNT-06 (found 2026-10-10): both index pages built the vehicle filter dropdown from
| Vehicle::withoutTenancy()->get(), so every tenant saw every other tenant's unit numbers, makes and models.
*/

describe('MNT-01: vehicle_id must belong to the tenant', function () {
    it('rejects a service log for another company\'s vehicle', function () {
        [$owner] = Maintenance::tenantWithVehicle();
        [, $foreign] = Maintenance::tenantWithVehicle(['odometer' => 100]);

        $this->actingAs($owner)
            ->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($foreign, ['current_odometer' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');

        expect(ServiceLog::withoutTenancy()->count())->toBe(0)
            ->and($foreign->fresh()->odometer)->toBe(100);
    });

    it('rejects moving a service log to another company\'s vehicle', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        [, $foreign] = Maintenance::tenantWithVehicle();
        $log = ServiceLogFactory::new()->forVehicle($vehicle)->create();
        $log->maintenanceCategories()->attach(Maintenance::category());

        $this->actingAs($owner)
            ->putJson(route('admin.service-log.update', $log->id), Maintenance::serviceLogPayload($foreign))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');

        expect($log->fresh()->vehicle_id)->toBe($vehicle->id);
    });

    it('rejects a schedule for another company\'s vehicle', function () {
        [$owner] = Maintenance::tenantWithVehicle();
        [, $foreign] = Maintenance::tenantWithVehicle();

        $this->actingAs($owner)
            ->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload($foreign))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');

        expect(MaintenanceSchedule::withoutTenancy()->count())->toBe(0);
    });

    it('rejects moving a schedule to another company\'s vehicle', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        [, $foreign] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create();

        $this->actingAs($owner)
            ->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload($foreign))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');

        expect($schedule->fresh()->vehicle_id)->toBe($vehicle->id);
    });

    it('rejects a deleted vehicle of the tenant\'s own company', function (string $route) {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $vehicle->delete();
        $payload = $route === 'admin.service-log.store'
            ? Maintenance::serviceLogPayload($vehicle)
            : Maintenance::schedulePayload($vehicle);

        $this->actingAs($owner)->postJson(route($route), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vehicle_id');
    })->with(['admin.service-log.store', 'admin.maintenance-schedule.store']);

    it('still accepts the tenant\'s own vehicle', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle(['odometer' => 100]);

        $this->actingAs($owner)
            ->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['current_odometer' => 2000]))
            ->assertOk();
        $this->actingAs($owner)
            ->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload($vehicle))
            ->assertOk();

        expect(ServiceLog::withoutTenancy()->sole()->vehicle_id)->toBe($vehicle->id)
            ->and(MaintenanceSchedule::withoutTenancy()->sole()->vehicle_id)->toBe($vehicle->id)
            ->and($vehicle->fresh()->odometer)->toBe(2000);
    });

    it('still lets a tenant create a schedule for all vehicles', function () {
        [$owner] = Maintenance::tenantWithVehicle();

        $this->actingAs($owner)
            ->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload(null))
            ->assertOk();

        expect(MaintenanceSchedule::withoutTenancy()->sole()->vehicle_id)->toBeNull();
    });

    it('still lets a super-admin use any company\'s vehicle', function () {
        [, $vehicle] = Maintenance::tenantWithVehicle();

        $this->actingAs(Actors::superAdmin())
            ->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle))
            ->assertOk();

        expect(ServiceLog::withoutTenancy()->sole()->vehicle_id)->toBe($vehicle->id);
    });
});

describe('MNT-06: the index filter lists only the tenant\'s vehicles', function () {
    it('does not show another company\'s vehicles', function (string $route) {
        [$owner] = Maintenance::tenantWithVehicle(['unit_no' => 'MINE-001']);
        Maintenance::tenantWithVehicle(['unit_no' => 'THEIRS-001']);

        $this->actingAs($owner)->get(route($route))
            ->assertOk()
            ->assertSee('MINE-001')
            ->assertDontSee('THEIRS-001');
    })->with(['admin.service-log.index', 'admin.maintenance-schedule.index']);

    it('still shows a super-admin every company\'s vehicles', function (string $route) {
        Maintenance::tenantWithVehicle(['unit_no' => 'ONE-001']);
        Maintenance::tenantWithVehicle(['unit_no' => 'TWO-001']);

        $this->actingAs(Actors::superAdmin())->get(route($route))
            ->assertOk()
            ->assertSee('ONE-001')
            ->assertSee('TWO-001');
    })->with(['admin.service-log.index', 'admin.maintenance-schedule.index']);
});

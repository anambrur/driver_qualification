<?php

use App\Models\MaintenanceSchedule;
use App\Models\ServiceLog;
use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\ServiceLogFactory;
use Tests\Feature\Modules\Maintenance\Maintenance;
use Tests\Support\Actors;

/*
| MNT-03: super-admin-created logs/schedules were saved with the super-admin's own company_id.
| store() was fixed by ACL-05 (access-control, 2026-10-09). update() still kept the old
| company when a super-admin moved a record to another company's vehicle, and the schedule's
| update($request->all()) let a super-admin post any company_id (or next_due_*) directly.
*/

describe('MNT-03: records belong to their vehicle\'s company', function () {
    it('files a super-admin\'s new log and schedule under the vehicle\'s company', function () {
        [, $vehicle] = Maintenance::tenantWithVehicle();

        $this->actingAs(Actors::superAdmin())->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle))->assertOk();
        $this->actingAs(Actors::superAdmin())->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload($vehicle))->assertOk();

        expect(ServiceLog::sole()->company_id)->toBe($vehicle->company_id)
            ->and(MaintenanceSchedule::sole()->company_id)->toBe($vehicle->company_id);
    });

    it('moves a service log to the new vehicle\'s company when a super-admin changes the vehicle', function () {
        [, $from] = Maintenance::tenantWithVehicle();
        [, $to] = Maintenance::tenantWithVehicle();
        $log = ServiceLogFactory::new()->forVehicle($from)->create();

        $this->actingAs(Actors::superAdmin())
            ->putJson(route('admin.service-log.update', $log->id), Maintenance::serviceLogPayload($to))
            ->assertOk();

        expect($log->fresh())->vehicle_id->toBe($to->id)->company_id->toBe($to->company_id);
    });

    it('moves a schedule to the new vehicle\'s company when a super-admin changes the vehicle', function () {
        [, $from] = Maintenance::tenantWithVehicle();
        [, $to] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($from)->create();

        $this->actingAs(Actors::superAdmin())
            ->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload($to))
            ->assertOk();

        expect($schedule->fresh())->vehicle_id->toBe($to->id)->company_id->toBe($to->company_id);
    });

    it('keeps a schedule\'s company when a super-admin makes it apply to all vehicles', function () {
        [, $vehicle] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create();

        $this->actingAs(Actors::superAdmin())
            ->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload(null))
            ->assertOk();

        expect($schedule->fresh())->vehicle_id->toBeNull()->company_id->toBe($vehicle->company_id);
    });

    it('ignores company_id and computed fields posted to a schedule update', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        [$other] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create(['next_due_date' => '2026-12-01']);

        foreach ([$owner, Actors::superAdmin()] as $user) {
            $this->actingAs($user)
                ->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload($vehicle, [
                    'company_id' => Actors::companyOf($other)->id,
                    'next_due_date' => '2099-01-01',
                    'is_active' => false,
                ]))
                ->assertOk();

            expect($schedule->fresh())
                ->company_id->toBe($vehicle->company_id)
                ->next_due_date->toDateString()->toBe('2026-12-01')
                ->is_active->toBeTrue();
        }
    });

    it('keeps a tenant\'s records in the tenant\'s company on update', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $other = Maintenance::vehicleOf($owner);
        $log = ServiceLogFactory::new()->forVehicle($vehicle)->create();

        $this->actingAs($owner)
            ->putJson(route('admin.service-log.update', $log->id), Maintenance::serviceLogPayload($other, ['company_id' => 999]))
            ->assertOk();

        expect($log->fresh())->vehicle_id->toBe($other->id)->company_id->toBe($vehicle->company_id);
    });
});

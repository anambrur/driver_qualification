<?php

use App\Models\MaintenanceSchedule;
use App\Models\ServiceLog;
use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\ServiceLogFactory;
use Tests\Feature\Modules\Maintenance\Maintenance;
use Tests\Support\Actors;

/*
| MNT-03: logs/schedules were saved with the acting user's company instead of the vehicle's,
| and the schedule's update($request->all()) accepted any posted company_id (or next_due_*).
| Under tenancy (11 §5) the vehicle is always the tenant's own, a foreign vehicle is refused,
| and moving records between companies is the platform's audited Asset Transfer (PO-08).
*/

describe('MNT-03: records belong to their vehicle\'s company', function () {
    it('files a new log and schedule under the vehicle\'s company', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();

        $this->actingAs($owner)->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle))->assertOk();
        $this->actingAs($owner)->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload($vehicle))->assertOk();

        expect(ServiceLog::withoutTenancy()->sole()->company_id)->toBe($vehicle->company_id)
            ->and(MaintenanceSchedule::withoutTenancy()->sole()->company_id)->toBe($vehicle->company_id);
    });

    it('refuses to move a service log to another company\'s vehicle', function () {
        [$owner, $from] = Maintenance::tenantWithVehicle();
        [, $to] = Maintenance::tenantWithVehicle();
        $log = ServiceLogFactory::new()->forVehicle($from)->create();

        $this->actingAs($owner)
            ->putJson(route('admin.service-log.update', $log->id), Maintenance::serviceLogPayload($to))
            ->assertStatus(422);

        expect(ServiceLog::withoutTenancy()->find($log->id))->vehicle_id->toBe($from->id)->company_id->toBe($from->company_id);
    });

    it('refuses to move a schedule to another company\'s vehicle', function () {
        [$owner, $from] = Maintenance::tenantWithVehicle();
        [, $to] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($from)->create();

        $this->actingAs($owner)
            ->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload($to))
            ->assertStatus(422);

        expect(MaintenanceSchedule::withoutTenancy()->find($schedule->id))->vehicle_id->toBe($from->id)->company_id->toBe($from->company_id);
    });

    it('keeps a schedule\'s company when it is made to apply to all vehicles', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create();

        $this->actingAs($owner)
            ->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload(null))
            ->assertOk();

        expect(MaintenanceSchedule::withoutTenancy()->find($schedule->id))->vehicle_id->toBeNull()->company_id->toBe($vehicle->company_id);
    });

    it('ignores company_id and computed fields posted to a schedule update', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        [$other] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create(['next_due_date' => '2026-12-01']);

        $this->actingAs($owner)
            ->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload($vehicle, [
                'company_id' => Actors::companyOf($other)->id,
                'next_due_date' => '2099-01-01',
                'is_active' => false,
            ]))
            ->assertOk();

        expect(MaintenanceSchedule::withoutTenancy()->find($schedule->id))
            ->company_id->toBe($vehicle->company_id)
            ->next_due_date->toDateString()->toBe('2026-12-01')
            ->status->toBe('active');
    });

    it('keeps a tenant\'s records in the tenant\'s company on update', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $other = Maintenance::vehicleOf($owner);
        $log = ServiceLogFactory::new()->forVehicle($vehicle)->create();

        $this->actingAs($owner)
            ->putJson(route('admin.service-log.update', $log->id), Maintenance::serviceLogPayload($other, ['company_id' => 999]))
            ->assertOk();

        expect(ServiceLog::withoutTenancy()->find($log->id))->vehicle_id->toBe($other->id)->company_id->toBe($vehicle->company_id);
    });
});

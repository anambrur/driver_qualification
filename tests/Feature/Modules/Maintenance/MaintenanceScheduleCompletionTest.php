<?php

use Database\Factories\MaintenanceScheduleFactory;
use Illuminate\Support\Carbon;
use Tests\Feature\Modules\Maintenance\Maintenance;

/*
| MNT-04: markAsCompleted() set last_due_hours from $vehicle->engine_hours, a column vehicles
| doesn't have, so it was always 0 (and getVehicleDetails() always reported 0 engine hours).
| Also covered here: the details modal read is_due, status_badge, interval_text, ... from
| show(), which never sent them, so "Mark as completed" never appeared; and every edit
| recalculated the next due date from today, so changing the notes moved the due date.
*/

afterEach(function () {
    Carbon::setTestNow();
});

describe('MNT-04: completing a schedule', function () {
    it('records the logged engine hours and schedules the next service from them', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        // A service log raises the vehicle's engine hours (PO-05)
        $this->actingAs($owner)->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['engine_hours_at_service' => 1200, 'current_engine_hours' => 1200]))->assertOk();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->engineHours(250)->create(['next_due_hours' => 1100]);

        $this->actingAs($owner)->postJson(route('admin.maintenance-schedule.mark-completed', $schedule->id))->assertOk();

        expect($schedule->fresh())->last_due_hours->toBe(1200)->next_due_hours->toBe(1450);
    });

    it('records the odometer for a mileage schedule', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle(['odometer' => 52000]);
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->mileage(5000)->create(['next_due_mileage' => 50000]);

        $this->actingAs($owner)->postJson(route('admin.maintenance-schedule.mark-completed', $schedule->id))->assertOk();

        expect($schedule->fresh())->last_due_mileage->toBe(52000)->next_due_mileage->toBe(57000);
    });

    it('records today for a date schedule', function () {
        Carbon::setTestNow('2026-10-10 12:00:00');
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create(['interval_days' => 30, 'next_due_date' => '2026-10-01']);

        $this->actingAs($owner)->postJson(route('admin.maintenance-schedule.mark-completed', $schedule->id))->assertOk();

        expect($schedule->fresh())
            ->last_due_date->toDateString()->toBe('2026-10-10')
            ->next_due_date->toDateString()->toBe('2026-11-09');
    });

    it('refuses another tenant\'s schedule', function () {
        [$owner] = Maintenance::tenantWithVehicle();
        [, $foreign] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($foreign)->create(['next_due_date' => '2026-10-01']);

        $this->actingAs($owner)->postJson(route('admin.maintenance-schedule.mark-completed', $schedule->id))->assertForbidden();

        expect($schedule->fresh()->last_due_date)->toBeNull();
    });

    it('reports the logged engine hours in the vehicle details', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $this->actingAs($owner)->postJson(route('admin.service-log.store'), Maintenance::serviceLogPayload($vehicle, ['engine_hours_at_service' => 777, 'current_engine_hours' => 777]))->assertOk();

        $this->actingAs($owner)->getJson(route('admin.maintenance-schedule.get-vehicle-details', $vehicle->id))
            ->assertOk()
            ->assertJsonPath('data.engine_hours', 777);
    });
});

describe('MNT-04: the details modal', function () {
    it('gets the fields it shows, including whether the schedule is due', function () {
        Carbon::setTestNow('2026-10-10 12:00:00');
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create(['interval_days' => 30, 'next_due_date' => '2026-10-01']);

        $this->actingAs($owner)->getJson(route('admin.maintenance-schedule.show', $schedule->id))
            ->assertOk()
            ->assertJsonPath('data.is_due', true)
            ->assertJsonPath('data.schedule_type_label', 'By Date')
            ->assertJsonPath('data.interval_text', 'Every 30 days')
            ->assertJsonPath('data.next_due_text', 'Date: Oct 01, 2026')
            ->assertJsonPath('data.vehicle.full_name', $vehicle->full_name);
    });
});

describe('MNT-04: editing a schedule', function () {
    it('keeps the due date when only the notes change', function () {
        Carbon::setTestNow('2026-10-01 12:00:00');
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $this->actingAs($owner)->postJson(route('admin.maintenance-schedule.store'), Maintenance::schedulePayload($vehicle, ['interval_days' => 30]))->assertOk();
        $schedule = \App\Models\MaintenanceSchedule::sole();
        expect($schedule->next_due_date->toDateString())->toBe('2026-10-31');

        Carbon::setTestNow('2026-10-20 12:00:00');
        $this->actingAs($owner)->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload($vehicle, [
            'maintenance_category_id' => $schedule->maintenance_category_id,
            'interval_days' => 30,
            'notes' => 'Changed only the notes',
        ]))->assertOk();

        expect($schedule->fresh()->next_due_date->toDateString())->toBe('2026-10-31');
    });

    it('recalculates the due date when the interval changes', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle();
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create(['interval_days' => 30, 'last_due_date' => '2026-09-01', 'next_due_date' => '2026-10-01']);

        $this->actingAs($owner)->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload($vehicle, [
            'interval_days' => 60,
        ]))->assertOk();

        expect($schedule->fresh()->next_due_date->toDateString())->toBe('2026-10-31');
    });

    it('switches to mileage and drops the old due date', function () {
        [$owner, $vehicle] = Maintenance::tenantWithVehicle(['odometer' => 10000]);
        $schedule = MaintenanceScheduleFactory::new()->forVehicle($vehicle)->create(['interval_days' => 30, 'next_due_date' => '2026-10-01']);

        $this->actingAs($owner)->putJson(route('admin.maintenance-schedule.update', $schedule->id), Maintenance::schedulePayload($vehicle, [
            'schedule_type' => 'mileage',
            'interval_days' => 30,
            'interval_miles' => 5000,
        ]))->assertOk();

        expect($schedule->fresh())
            ->interval_days->toBeNull()
            ->next_due_date->toBeNull()
            ->next_due_mileage->toBe(15000);
    });
});

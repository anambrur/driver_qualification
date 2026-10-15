<?php

use App\Models\MaintenanceSchedule;
use App\Tenancy\TenantContext;
use Database\Factories\MaintenanceScheduleFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Support\Carbon;

/*
| MNT-04: MaintenanceSchedule::calculateNextDue() and isDue(). Engine hours were read from
| $vehicle->engine_hours, a column vehicles doesn't have, so they were always 0. The only
| engine hours the app records are the service logs' current_engine_hours.
*/

afterEach(function () {
    Carbon::setTestNow();
});

function mntSchedule(array $attributes = [], ?callable $state = null): MaintenanceSchedule
{
    $factory = MaintenanceScheduleFactory::new();
    if ($state) {
        $factory = $state($factory);
    }

    $schedule = $factory->create(array_merge(['next_due_date' => null], $attributes));
    // The schedule reads its vehicle, a tenant model
    app(TenantContext::class)->set($schedule->company_id);

    return $schedule;
}

describe('MNT-04: date schedules', function () {
    it('is due interval days from now when it has never been done', function () {
        Carbon::setTestNow('2026-01-31 10:00:00');

        $schedule = mntSchedule(['interval_days' => 30])->calculateNextDue();

        expect($schedule->next_due_date->toDateString())->toBe('2026-03-02');
    });

    it('is due interval days after the last time it was done', function (string $last, int $days, string $next) {
        $schedule = mntSchedule(['interval_days' => $days, 'last_service_date' => $last])->calculateNextDue();

        expect($schedule->next_due_date->toDateString())->toBe($next)
            ->and($schedule->last_service_date->toDateString())->toBe($last);
    })->with([
        'month end' => ['2026-01-31', 30, '2026-03-02'],
        'into a leap day' => ['2028-02-28', 1, '2028-02-29'],
        'over the year end' => ['2026-12-31', 1, '2027-01-01'],
        'a year' => ['2026-03-01', 365, '2027-03-01'],
    ]);

    it('leaves the due date alone when there is no interval', function () {
        $schedule = mntSchedule(['interval_days' => null, 'next_due_date' => '2026-05-01'])->calculateNextDue();

        expect($schedule->next_due_date->toDateString())->toBe('2026-05-01');
    });

    it('is due on the due date, not the day before', function () {
        Carbon::setTestNow('2026-05-01 09:00:00');

        expect(mntSchedule(['next_due_date' => '2026-05-01'])->isDue())->toBeTrue()
            ->and(mntSchedule(['next_due_date' => '2026-05-02'])->isDue())->toBeFalse();
    });
});

describe('MNT-04: mileage schedules', function () {
    it('is due interval miles past the current odometer when never done', function () {
        $vehicle = VehicleFactory::new()->create(['odometer' => 10000]);

        $schedule = mntSchedule(['vehicle_id' => $vehicle->id, 'company_id' => $vehicle->company_id], fn ($f) => $f->mileage(5000))->calculateNextDue();

        expect($schedule->next_due_mileage)->toBe(15000);
    });

    it('is due interval miles past the last service', function () {
        $vehicle = VehicleFactory::new()->create(['odometer' => 14000]);

        $schedule = mntSchedule(['vehicle_id' => $vehicle->id, 'company_id' => $vehicle->company_id, 'last_service_mileage' => 12000], fn ($f) => $f->mileage(5000))->calculateNextDue();

        expect($schedule->next_due_mileage)->toBe(17000)
            ->and($schedule->isDue())->toBeFalse();

        $schedule->save();
        $vehicle->update(['odometer' => 17000]);
        expect($schedule->fresh()->isDue())->toBeTrue();
    });

    it('cannot be calculated for a schedule with no vehicle', function () {
        $schedule = mntSchedule(['vehicle_id' => null], fn ($f) => $f->mileage(5000))->calculateNextDue();

        expect($schedule->next_due_mileage)->toBeNull()
            ->and($schedule->isDue())->toBeFalse();
    });
});

describe('MNT-04: engine hour schedules', function () {
    it('uses the vehicle\'s engine hours (PO-05)', function () {
        $vehicle = VehicleFactory::new()->create(['engine_hours' => 1200]);

        $schedule = mntSchedule(['vehicle_id' => $vehicle->id, 'company_id' => $vehicle->company_id], fn ($f) => $f->engineHours(250))->calculateNextDue();

        expect($schedule->next_due_hours)->toBe(1450);
    });

    it('is due once the vehicle\'s engine hours reach the next due hours', function () {
        $vehicle = VehicleFactory::new()->create(['engine_hours' => 1000]);
        $schedule = mntSchedule(['vehicle_id' => $vehicle->id, 'company_id' => $vehicle->company_id, 'next_due_hours' => 1100], fn ($f) => $f->engineHours(250));

        expect($schedule->isDue())->toBeFalse();

        $vehicle->update(['engine_hours' => 1100]);
        expect($schedule->fresh()->isDue())->toBeTrue();
    });

    it('starts from zero hours for a vehicle with no logged hours', function () {
        $vehicle = VehicleFactory::new()->create();

        $schedule = mntSchedule(['vehicle_id' => $vehicle->id, 'company_id' => $vehicle->company_id], fn ($f) => $f->engineHours(250))->calculateNextDue();

        expect($schedule->next_due_hours)->toBe(250);
    });

    it('is never due without a vehicle', function () {
        $schedule = mntSchedule(['vehicle_id' => null, 'next_due_hours' => 10], fn ($f) => $f->engineHours(250));

        expect($schedule->isDue())->toBeFalse();
    });
});

describe('MNT-04: changing the schedule type', function () {
    it('clears the next due values of the other types', function () {
        $vehicle = VehicleFactory::new()->create(['odometer' => 10000]);
        $schedule = mntSchedule(['vehicle_id' => $vehicle->id, 'company_id' => $vehicle->company_id, 'next_due_date' => '2026-05-01', 'next_due_hours' => 300]);

        $schedule->fill(['schedule_type' => 'mileage', 'interval_days' => null, 'interval_miles' => 5000])->calculateNextDue();

        expect($schedule->next_due_mileage)->toBe(15000)
            ->and($schedule->next_due_date)->toBeNull()
            ->and($schedule->next_due_hours)->toBeNull()
            ->and($schedule->next_due_text)->toBe('Mileage: 15,000 mi');
    });
});

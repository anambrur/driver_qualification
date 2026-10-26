<?php

use App\Models\AuditLog;
use App\Models\Trip;
use App\Models\TripStop;
use App\Models\Vehicle;
use App\Services\Operations\TripAssignmentService;
use App\Services\Operations\TripLifecycleService;
use App\Services\Operations\TripService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Modules\Shippers\Shippers;
use Tests\Feature\Modules\Trips\Trips;
use Tests\Support\Actors;

/*
| The trip on the road (10 §13 steps 3–5): start (equipment confirmed, one in-progress trip per
| driver and per vehicle), picked up, stop times, complete; cancel only before the start.
*/

beforeEach(function () {
    Shippers::seedStates();
    // 13:00 in Chicago (the default company time zone), so "today" is the same date everywhere
    Carbon::setTestNow('2026-10-26 18:00:00');
    $this->company = Actors::company();
    $this->owner = $this->company->owner;
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * A trip in the given state, through the services.
 */
function tripIn(string $state, bool $alsoPickup = false): Trip
{
    $company = test()->company;

    return match ($state) {
        'draft' => Trips::create($company),
        'planned' => Trips::planned($company, alsoPickup: $alsoPickup),
        'assigned (unconfirmed)' => Trips::assigned($company, confirmed: false, alsoPickup: $alsoPickup),
        'assigned' => Trips::assigned($company, alsoPickup: $alsoPickup),
        'in_progress' => Trips::inProgress($company, alsoPickup: $alsoPickup),
        'completed' => Trips::operate($company, fn ($a, TripLifecycleService $lifecycle) => $lifecycle->complete(Trips::inProgress($company), null, $company->owner)->withoutRelations()),
        'cancelled' => Trips::service($company, fn (TripService $trips) => $trips->cancel(Trips::planned($company), 'No load', $company->owner)),
    };
}

/**
 * Each action on a trip, as the services run it.
 *
 * @return array<string, Closure(Trip): mixed>
 */
function tripActions(): array
{
    $company = test()->company;
    $owner = $company->owner;
    $run = fn (Closure $callback) => Trips::operate($company, $callback);

    return [
        'assign' => function (Trip $trip) use ($run, $company, $owner) {
            $crew = Trips::crew($company);

            return $run(fn (TripAssignmentService $a) => $a->assign($trip, ['driver_id' => $crew['driver']->id, 'vehicle_id' => $crew['vehicle']->id], $owner));
        },
        'unassign' => fn (Trip $trip) => $run(fn (TripAssignmentService $a) => $a->unassign($trip, $owner)),
        'confirm equipment' => fn (Trip $trip) => $run(fn (TripAssignmentService $a) => $a->confirmEquipment($trip, $owner)),
        'start' => fn (Trip $trip) => $run(fn ($a, TripLifecycleService $l) => $l->start($trip, $owner)),
        'picked up' => fn (Trip $trip) => $run(fn ($a, TripLifecycleService $l) => $l->pickedUp($trip, $owner)),
        'complete' => fn (Trip $trip) => $run(fn ($a, TripLifecycleService $l) => $l->complete($trip, null, $owner)),
        'cancel' => fn (Trip $trip) => Trips::service($company, fn (TripService $s) => $s->cancel($trip, 'No load', $owner)),
    ];
}

describe('the state-transition table', function () {
    // Status → the actions it allows; every other action is refused and changes nothing
    $allowed = [
        'draft' => ['cancel' => 'cancelled'],
        'planned' => ['assign' => 'assigned', 'cancel' => 'cancelled'],
        'assigned (unconfirmed)' => ['assign' => 'assigned', 'unassign' => 'planned', 'confirm equipment' => 'assigned', 'cancel' => 'cancelled'],
        'assigned' => ['assign' => 'assigned', 'unassign' => 'planned', 'start' => 'in_progress', 'cancel' => 'cancelled'],
        'in_progress' => ['picked up' => 'in_progress', 'complete' => 'completed'],
        'completed' => [],
        'cancelled' => [],
    ];
    $cases = [];
    foreach ($allowed as $state => $actions) {
        foreach (['assign', 'unassign', 'confirm equipment', 'start', 'picked up', 'complete', 'cancel'] as $action) {
            $cases["{$state} → {$action}"] = [$state, $action, $actions[$action] ?? null];
        }
    }

    it('allows only the transitions of 10 §13', function (string $state, string $action, ?string $to) {
        // The start is the pickup, so "picked up" has a stop; "complete" needs it picked up first
        $trip = tripIn($state, alsoPickup: $action === 'picked up');
        if ($action === 'complete' && $state === 'in_progress') {
            $trip = Trips::inProgress($this->company);
        }
        $before = $trip->fresh()->status;

        if ($to === null) {
            expect(fn () => tripActions()[$action]($trip))->toThrow(ValidationException::class)
                ->and($trip->fresh()->status)->toBe($before);
        } else {
            tripActions()[$action]($trip);
            expect($trip->fresh()->status)->toBe($to);
        }
    })->with($cases);
});

it('starts an assigned trip with the unit numbers as they are at the start', function () {
    $crew = Trips::crew($this->company);
    $trip = Trips::assigned($this->company, $crew);
    $this->actingAs(Actors::dispatcher($this->company));

    $this->post(route('trips.start', $trip))->assertRedirect(route('trips.show', $trip))->assertSessionHasNoErrors();
    Vehicle::withoutTenancy()->whereKey($crew['vehicle']->id)->update(['unit_no' => 'RENAMED-1']);

    $trip->refresh();
    expect($trip->status)->toBe('in_progress')
        ->and($trip->started_at->equalTo(now()))->toBeTrue()
        ->and($trip->vehicle_unit_no_snapshot)->toBe($crew['vehicle']->unit_no)
        ->and($trip->trailer_unit_no_snapshot)->toBe($crew['trailer']->unit_no);

    $audit = AuditLog::query()->where('action', 'trip.started')->where('subject_id', $trip->id)->sole();
    expect($audit->changes['vehicle_unit_no_snapshot'])->toBe($crew['vehicle']->unit_no);
});

it('needs the equipment confirmed before the start', function () {
    $trip = Trips::assigned($this->company, confirmed: false);
    $this->actingAs(Actors::dispatcher($this->company));

    $this->postJson(route('trips.start', $trip))->assertUnprocessable()
        ->assertJsonPath('errors.trip.0', 'Confirm the tractor and trailer before starting the trip.');
    $this->get(route('trips.show', $trip))->assertSee('title="Confirm the tractor and trailer first"', false);

    expect($trip->fresh()->status)->toBe('assigned');
});

describe('one in-progress trip per driver and per vehicle (acceptance)', function () {
    it('refuses a second in-progress trip for the same driver', function () {
        $crew = Trips::crew($this->company);
        $first = Trips::inProgress($this->company, $crew);
        $second = Trips::assigned($this->company, ['vehicle' => Trips::crew($this->company)['vehicle']] + $crew);

        expect(fn () => Trips::operate($this->company, fn ($a, TripLifecycleService $l) => $l->start($second, $this->owner)))
            ->toThrow(ValidationException::class, "This driver already has a trip in progress ({$first->label()}).");

        $this->actingAs(Actors::dispatcher($this->company));
        $this->postJson(route('trips.start', $second))->assertUnprocessable()
            ->assertJsonPath('errors.trip.0', "This driver already has a trip in progress ({$first->label()}).");

        expect($second->fresh()->status)->toBe('assigned')
            ->and(Trip::withoutTenancy()->where('status', 'in_progress')->where('driver_id', $crew['driver']->id)->count())->toBe(1);
    });

    it('refuses a second in-progress trip on the same vehicle', function () {
        $crew = Trips::crew($this->company);
        $first = Trips::inProgress($this->company, $crew);
        $second = Trips::assigned($this->company, ['driver' => Trips::crew($this->company)['driver']] + $crew);

        $this->actingAs(Actors::dispatcher($this->company));
        $this->postJson(route('trips.start', $second))->assertUnprocessable()
            ->assertJsonPath('errors.trip.0', "This vehicle is already on a trip in progress ({$first->label()}).");

        expect($second->fresh()->status)->toBe('assigned');
    });

    it('lets the driver start the next trip once the first is completed', function () {
        $crew = Trips::crew($this->company);
        $first = Trips::inProgress($this->company, $crew);
        $second = Trips::assigned($this->company, $crew);
        Trips::operate($this->company, fn ($a, TripLifecycleService $l) => $l->complete($first, 100.0, $this->owner));

        Trips::operate($this->company, fn ($a, TripLifecycleService $l) => $l->start($second, $this->owner));

        expect($second->fresh()->status)->toBe('in_progress');
    });

    it('frees nothing to free when an assigned trip is cancelled, and the driver starts another', function () {
        $crew = Trips::crew($this->company);
        $cancelled = Trips::assigned($this->company, $crew);
        $next = Trips::assigned($this->company, $crew);
        Trips::service($this->company, fn (TripService $trips) => $trips->cancel($cancelled, 'Shipper cancelled', $this->owner));

        Trips::operate($this->company, fn ($a, TripLifecycleService $l) => $l->start($next, $this->owner));

        $row = Trip::withoutTenancy()->find($cancelled->id, ['active_driver_id', 'active_vehicle_id']);
        expect([$row->active_driver_id, $row->active_vehicle_id])->toBe([null, null])
            ->and($next->fresh()->status)->toBe('in_progress');
    });
});

it('refuses to cancel a trip in progress', function () {
    $trip = Trips::inProgress($this->company);
    $this->actingAs(Actors::dispatcher($this->company));

    $this->postJson(route('trips.cancel', $trip), ['cancel_reason' => 'Changed my mind'])->assertUnprocessable();
    $this->get(route('trips.show', $trip))->assertDontSee(route('trips.cancel', $trip));

    expect($trip->fresh()->status)->toBe('in_progress');
});

it('refuses the start when the driver or an asset is no longer assignable', function (Closure $break) {
    $crew = Trips::crew($this->company);
    $trip = Trips::assigned($this->company, $crew);
    $break($crew);

    expect(fn () => Trips::operate($this->company, fn ($a, TripLifecycleService $l) => $l->start($trip, $this->owner)))
        ->toThrow(ValidationException::class, 'Reassign the trip first.');
})->with([
    'terminated driver' => [fn ($crew) => $crew['driver']->forceFill(['employment_status' => 'terminated'])->save()],
    'transferred-out vehicle' => [fn ($crew) => $crew['vehicle']->forceFill(['transferred_out_at' => now()])->save()],
    'deleted trailer' => [fn ($crew) => $crew['trailer']->forceFill(['deleted_at' => now()])->save()],
]);

describe('picked up', function () {
    it('departs the pickup stop', function () {
        $trip = Trips::inProgress($this->company, pickup: true);
        $this->actingAs(Actors::dispatcher($this->company));
        $this->get(route('trips.show', $trip))->assertSee(route('trips.picked-up', $trip));

        $this->post(route('trips.picked-up', $trip))->assertSessionHasNoErrors();

        $pickup = TripStop::withoutTenancy()->where('trip_id', $trip->id)->where('stop_type', 'pickup')->sole();
        expect($pickup->departed_at->equalTo(now()))->toBeTrue()->and($pickup->arrived_at)->not->toBeNull()
            ->and(AuditLog::query()->where('action', 'trip.picked_up')->where('subject_id', $trip->id)->exists())->toBeTrue();
        $this->get(route('trips.show', $trip))->assertDontSee(route('trips.picked-up', $trip));
        $this->postJson(route('trips.picked-up', $trip))->assertUnprocessable()->assertJsonPath('errors.trip.0', 'The load is already picked up.');
    });

    it('departs the start when it is also the pickup', function () {
        $trip = Trips::inProgress($this->company, alsoPickup: true);

        Trips::operate($this->company, fn ($a, TripLifecycleService $l) => $l->pickedUp($trip, $this->owner));

        $start = TripStop::withoutTenancy()->where('trip_id', $trip->id)->where('stop_type', 'start')->sole();
        expect($start->departed_at)->not->toBeNull();
    });

    it('has nothing to pick up without a pickup stop', function () {
        $trip = Trips::inProgress($this->company);
        $this->actingAs(Actors::dispatcher($this->company));

        $this->get(route('trips.show', $trip))->assertDontSee(route('trips.picked-up', $trip));
        $this->postJson(route('trips.picked-up', $trip))->assertUnprocessable()->assertJsonPath('errors.trip.0', 'This trip has no pickup stop.');
    });
});

describe('stop times', function () {
    beforeEach(function () {
        $this->trip = Trips::inProgress($this->company, pickup: true);
        $this->stops = TripStop::withoutTenancy()->where('trip_id', $this->trip->id)->get()->keyBy('stop_type');
        $this->actingAs(Actors::dispatcher($this->company));
    });

    it('stores times typed in the company time zone as UTC, and records the pickup', function () {
        $this->put(route('trips.stop-times.update', $this->trip), ['stops' => [
            $this->stops['pickup']->id => ['arrived_at' => '2026-10-26T09:15', 'departed_at' => '2026-10-26T10:00'],
        ]])->assertRedirect(route('trips.show', $this->trip).'#stops')->assertSessionHasNoErrors();

        $pickup = $this->stops['pickup']->fresh();
        expect($pickup->arrived_at->toDateTimeString())->toBe('2026-10-26 14:15:00')
            ->and($pickup->departed_at->toDateTimeString())->toBe('2026-10-26 15:00:00')
            ->and(AuditLog::query()->where('action', 'trip.picked_up')->where('subject_id', $this->trip->id)->count())->toBe(1);

        $this->get(route('trips.show', $this->trip))->assertSee('value="2026-10-26T09:15"', false)->assertSee('Arrived Oct 26, 9:15 AM');
    });

    it('clears a time left empty', function () {
        $this->stops['drop_off']->forceFill(['arrived_at' => now()])->save();

        $this->put(route('trips.stop-times.update', $this->trip), ['stops' => [$this->stops['drop_off']->id => ['arrived_at' => '', 'departed_at' => '']]])->assertSessionHasNoErrors();

        expect($this->stops['drop_off']->fresh()->arrived_at)->toBeNull();
    });

    it('refuses a departure before the arrival, a time in the future or before the trip date', function (array $times, string $field) {
        $id = $this->stops['pickup']->id;

        $this->putJson(route('trips.stop-times.update', $this->trip), ['stops' => [$id => $times]])
            ->assertUnprocessable()->assertJsonValidationErrors("stops.{$id}.{$field}");

        expect($this->stops['pickup']->fresh()->departed_at)->toBeNull();
    })->with([
        'departure before arrival' => [['arrived_at' => '2026-10-26T10:00', 'departed_at' => '2026-10-26T09:00'], 'departed_at'],
        'in the future' => [['arrived_at' => '2026-10-26T13:30', 'departed_at' => null], 'arrived_at'],
        'before the trip date' => [['arrived_at' => '2026-10-25T23:00', 'departed_at' => null], 'arrived_at'],
        'not a time' => [['arrived_at' => 'yesterday', 'departed_at' => null], 'arrived_at'],
    ]);

    it('accepts times from the start when the trip started before its date', function () {
        Trip::withoutTenancy()->whereKey($this->trip->id)->update(['trip_date' => '2026-10-27']);

        $this->put(route('trips.stop-times.update', $this->trip), ['stops' => [
            $this->stops['pickup']->id => ['arrived_at' => '2026-10-26T13:00', 'departed_at' => null],
        ]])->assertSessionHasNoErrors();

        expect($this->stops['pickup']->fresh()->arrived_at)->not->toBeNull();
    });

    it('opens the Stops tab with the error when a save is refused', function () {
        $id = $this->stops['pickup']->id;

        $this->from(route('trips.show', $this->trip))
            ->put(route('trips.stop-times.update', $this->trip), ['stops' => [$id => ['arrived_at' => '2026-10-26T10:00', 'departed_at' => '2026-10-26T09:00']]])
            ->assertSessionHasErrors("stops.{$id}.departed_at");

        $html = $this->get(route('trips.show', $this->trip))->assertSee('the departure can&#039;t be before the arrival', false)->getContent();

        expect($html)->toMatch('/id="trip-tabs-panel-stops"[^>]*tabindex="-1"\s+class/')
            ->and($html)->toMatch('/id="trip-tabs-panel-details"[^>]*tabindex="-1"\s+hidden/');
    });

    it('refuses a stop of another trip', function () {
        $other = Trips::planned($this->company);
        $otherStop = TripStop::withoutTenancy()->where('trip_id', $other->id)->value('id');

        $this->putJson(route('trips.stop-times.update', $this->trip), ['stops' => [$otherStop => ['arrived_at' => '2026-10-26T09:00']]])
            ->assertUnprocessable()->assertJsonValidationErrors('stops');
    });

    it('changes times only while the trip is in progress', function () {
        $planned = Trips::planned($this->company);
        $stop = TripStop::withoutTenancy()->where('trip_id', $planned->id)->value('id');

        $this->putJson(route('trips.stop-times.update', $planned), ['stops' => [$stop => ['arrived_at' => '2026-10-26T09:00']]])
            ->assertUnprocessable()->assertJsonValidationErrors('trip');
        $this->get(route('trips.show', $planned))->assertDontSee('Save stop times');
    });
});

describe('complete', function () {
    it('completes with the typed miles and marks the drop-off reached', function () {
        $trip = Trips::inProgress($this->company);
        Trip::withoutTenancy()->whereKey($trip->id)->update(['planned_distance_miles' => 239.5]);
        $this->actingAs(Actors::dispatcher($this->company));
        $this->get(route('trips.show', $trip))->assertSee('name="actual_distance_miles"', false)->assertSee('value="239.5"', false);

        $this->post(route('trips.complete', $trip), ['actual_distance_miles' => '251.3'])->assertSessionHasNoErrors();

        $trip->refresh();
        expect($trip->status)->toBe('completed')
            ->and($trip->completed_at->equalTo(now()))->toBeTrue()
            ->and((float) $trip->actual_distance_miles)->toBe(251.3)
            ->and(TripStop::withoutTenancy()->where('trip_id', $trip->id)->where('stop_type', 'drop_off')->value('arrived_at'))->not->toBeNull()
            ->and(AuditLog::query()->where('action', 'trip.completed')->where('subject_id', $trip->id)->exists())->toBeTrue();
        $this->get(route('trips.show', $trip))->assertOk()->assertSee('251.3')->assertDontSee(route('trips.complete', $trip));
    });

    it('leaves the actual miles empty when none are typed', function () {
        $trip = Trips::inProgress($this->company);
        $this->actingAs(Actors::dispatcher($this->company));

        $this->post(route('trips.complete', $trip), ['actual_distance_miles' => ''])->assertSessionHasNoErrors();

        expect($trip->fresh()->actual_distance_miles)->toBeNull();
    });

    it('needs the load picked up first', function () {
        $trip = Trips::inProgress($this->company, pickup: true);
        $this->actingAs(Actors::dispatcher($this->company));

        $this->postJson(route('trips.complete', $trip))->assertUnprocessable()->assertJsonPath('errors.trip.0', 'Mark the load picked up first.');
        $this->postJson(route('trips.complete', $trip), ['actual_distance_miles' => '-1'])->assertJsonValidationErrors('actual_distance_miles');

        expect($trip->fresh()->status)->toBe('in_progress');
    });
});

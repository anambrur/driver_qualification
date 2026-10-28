<?php

namespace App\Services\Operations;

use App\Features\Feature;
use App\Models\AuditLog;
use App\Models\Trip;
use App\Models\TripStop;
use App\Models\User;
use App\Services\Entitlements\Entitlements;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An assigned trip on the road (10 §13 steps 3–5): start, picked up, stop times and complete.
 * One in-progress trip per driver and per vehicle is enforced by the unique generated
 * active_driver_id / active_vehicle_id columns; a clash becomes a validation error here.
 */
class TripLifecycleService
{
    /** Stop times may run a little ahead of the server clock. */
    private const FUTURE_GRACE_MINUTES = 5;

    public function __construct(private TripAssignmentService $assignment, private TripService $trips) {}

    /**
     * assigned → in_progress, with the unit numbers as they are now.
     */
    public function start(Trip $trip, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $actor) {
            $trip = $this->lock($trip);
            $this->assertStatus($trip, ['assigned'], 'Only an assigned trip can be started.');

            if ($trip->equipment_confirmed_at === null) {
                throw ValidationException::withMessages(['trip' => 'Confirm the tractor and trailer before starting the trip.']);
            }

            try {
                $this->assignment->assertEligible($trip->driver_id, $trip->vehicle_id, $trip->trailer_id);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['trip' => collect($e->errors())->flatten()->first().' Reassign the trip first.']);
            }

            $this->assertPreTripDone($trip);

            $trip->load(['vehicle:id,unit_no', 'trailer:id,unit_no']);
            $trip->forceFill([
                'status' => 'in_progress',
                'started_at' => now(),
                'vehicle_unit_no_snapshot' => $trip->vehicle?->unit_no,
                'trailer_unit_no_snapshot' => $trip->trailer?->unit_no,
            ]);

            try {
                $trip->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['trip' => $this->activeClash($trip)]);
            }

            AuditLog::record($actor, $trip->company_id, 'trip.started', $trip, [
                'driver_id' => $trip->driver_id,
                'vehicle_unit_no_snapshot' => $trip->vehicle_unit_no_snapshot,
                'trailer_unit_no_snapshot' => $trip->trailer_unit_no_snapshot,
            ]);

            return $trip;
        });
    }

    /**
     * The load is on board: the pickup stop (or the start, when it is the pickup) departs now.
     */
    public function pickedUp(Trip $trip, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $actor) {
            $trip = $this->lock($trip)->load('stops');
            $this->assertStatus($trip, ['in_progress'], 'Only a trip in progress can be picked up.');
            $stop = $trip->pickupStop() ?? throw ValidationException::withMessages(['trip' => 'This trip has no pickup stop.']);

            if ($stop->departed_at !== null) {
                throw ValidationException::withMessages(['trip' => 'The load is already picked up.']);
            }

            $stop->forceFill(['arrived_at' => $stop->arrived_at ?? now(), 'departed_at' => now()])->save();
            AuditLog::record($actor, $trip->company_id, 'trip.picked_up', $trip, ['stop_id' => $stop->id, 'departed_at' => $stop->departed_at->toIso8601String()]);

            return $trip;
        });
    }

    /**
     * Arrival and departure times typed in the company's time zone (Z1), stored in UTC. A blank
     * field clears the time.
     *
     * @param  array<int|string, array{arrived_at?: ?string, departed_at?: ?string}>  $times  validated TripStopTimesRequest data, keyed by stop id
     */
    public function saveStopTimes(Trip $trip, array $times, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $times, $actor) {
            $trip = $this->lock($trip)->load('stops');
            $this->assertStatus($trip, ['in_progress'], 'Stop times can be changed only while the trip is in progress.');
            $timezone = $this->trips->timezone();
            // A trip may be started before its date; times from then on stay valid
            $earliest = CarbonImmutable::parse($trip->trip_date->toDateString(), $timezone);
            if ($trip->started_at !== null && $trip->started_at->lt($earliest)) {
                $earliest = CarbonImmutable::instance($trip->started_at);
            }
            $latest = CarbonImmutable::now()->addMinutes(self::FUTURE_GRACE_MINUTES);
            $pickup = $trip->pickupStop();
            $wasPickedUp = $trip->isPickedUp();

            foreach ($times as $stopId => $values) {
                $stop = $trip->stops->firstWhere('id', (int) $stopId) ?? throw ValidationException::withMessages(['stops' => 'A stop does not belong to this trip.']);
                $label = TripStop::TYPES[$stop->stop_type];
                $parsed = [];

                foreach (['arrived_at', 'departed_at'] as $field) {
                    $value = $values[$field] ?? null;
                    $parsed[$field] = filled($value) ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value, $timezone)->utc() : null;

                    if ($parsed[$field] !== null && ($parsed[$field]->lt($earliest) || $parsed[$field]->gt($latest))) {
                        throw ValidationException::withMessages(["stops.{$stopId}.{$field}" => "{$label}: the time must be between the trip date (or the start) and now."]);
                    }
                }

                if ($parsed['arrived_at'] !== null && $parsed['departed_at'] !== null && $parsed['departed_at']->lt($parsed['arrived_at'])) {
                    throw ValidationException::withMessages(["stops.{$stopId}.departed_at" => "{$label}: the departure can't be before the arrival."]);
                }

                $stop->forceFill($parsed)->save();
            }

            if (! $wasPickedUp && $pickup !== null && $pickup->refresh()->departed_at !== null) {
                AuditLog::record($actor, $trip->company_id, 'trip.picked_up', $trip, ['stop_id' => $pickup->id, 'departed_at' => $pickup->departed_at->toIso8601String()]);
            }

            return $trip;
        });
    }

    /**
     * in_progress → completed: the drop-off is reached. Miles are typed in until GPS (Phase 3).
     */
    public function complete(Trip $trip, ?float $actualMiles, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $actualMiles, $actor) {
            $trip = $this->lock($trip)->load('stops');
            $this->assertStatus($trip, ['in_progress'], 'Only a trip in progress can be completed.');

            if ($trip->pickupStop() !== null && ! $trip->isPickedUp()) {
                throw ValidationException::withMessages(['trip' => 'Mark the load picked up first.']);
            }

            $dropOff = $trip->stop('drop_off');
            if ($dropOff !== null && $dropOff->arrived_at === null) {
                $dropOff->forceFill(['arrived_at' => now()])->save();
            }

            $trip->forceFill(['status' => 'completed', 'completed_at' => now(), 'actual_distance_miles' => $actualMiles])->save();
            AuditLog::record($actor, $trip->company_id, 'trip.completed', $trip, ['actual_distance_miles' => $actualMiles]);

            return $trip;
        });
    }

    /**
     * The pre-trip gate (P2-4): with the pre_trip_inspections feature, the latest completed
     * inspection of this trip with its current driver, vehicle and trailer must have found no
     * defects (396.13). Without the feature there is no gate.
     */
    private function assertPreTripDone(Trip $trip): void
    {
        $message = $this->preTripBlocker($trip);

        if ($message !== null) {
            throw ValidationException::withMessages(['trip' => $message]);
        }
    }

    /**
     * Why the pre-trip gate holds this assigned trip, or null when it may start. Also shown next
     * to the Start button.
     */
    public function preTripBlocker(Trip $trip): ?string
    {
        // Only while inspections can be recorded: a read-only feature must not block every start
        if (! Entitlements::for($trip->company()->firstOrFail())->state(Feature::PreTripInspections)->allowsWrite()) {
            return null;
        }

        $result = $trip->inspections()->where('status', 'completed')
            ->where('driver_id', $trip->driver_id)->where('vehicle_id', $trip->vehicle_id)
            ->when($trip->trailer_id === null, fn ($q) => $q->whereNull('trailer_id'), fn ($q) => $q->where('trailer_id', $trip->trailer_id))
            ->reorder()->latest('completed_at')->latest('id')->value('result');

        return match ($result) {
            'no_defects' => null,
            'defects_found' => 'The last pre-trip inspection found defects. Record a new inspection with no defects before starting the trip.',
            default => 'Complete the pre-trip inspection before starting the trip.',
        };
    }

    /**
     * Which one-active guard the start hit, as the message for the form. A locking read, so a
     * trip started by a concurrent request that has just committed is seen.
     */
    private function activeClash(Trip $trip): string
    {
        $active = fn (string $column, int $id) => Trip::query()->sharedLock()->where('status', 'in_progress')->where($column, $id)->whereKeyNot($trip->id)->value('trip_number');

        return match (true) {
            ($number = $active('driver_id', $trip->driver_id)) !== null => "This driver already has a trip in progress (#{$number}).",
            ($number = $active('vehicle_id', $trip->vehicle_id)) !== null => "This vehicle is already on a trip in progress (#{$number}).",
            default => 'This driver or vehicle already has a trip in progress.',
        };
    }

    /**
     * @param  list<string>  $statuses
     */
    private function assertStatus(Trip $trip, array $statuses, string $message): void
    {
        if (! in_array($trip->status, $statuses, true)) {
            throw ValidationException::withMessages(['trip' => $message]);
        }
    }

    private function lock(Trip $trip): Trip
    {
        return Trip::query()->lockForUpdate()->findOrFail($trip->id);
    }
}

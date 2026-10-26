<?php

namespace App\Services\Operations;

use App\Models\AssetGroup;
use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\Trailer;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Who drives which equipment on a trip (req 4.5, 4.6; 10 §13 step 2). Only active drivers and
 * assets that are neither deleted nor transferred out can be assigned; the form defaults to the
 * driver's active asset group. Staff confirm the equipment for the driver until the app (Phase 3).
 */
class TripAssignmentService
{
    /** @var array<int, array{driver: array<int, int>, vehicle: array<int, int>}> busy trips per trip id, for one request */
    private array $busy = [];

    public function __construct(private TripService $trips) {}

    /**
     * planned or assigned → assigned. An empty vehicle (and trailer) comes from the driver's
     * asset group. Any change of driver, vehicle or trailer clears the equipment confirmation.
     *
     * @param  array{driver_id: int|string, vehicle_id?: int|string|null, trailer_id?: int|string|null}  $data  validated AssignTripRequest data
     */
    public function assign(Trip $trip, array $data, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $data, $actor) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $this->assertStatus($trip, Trip::ASSIGNABLE, 'Only a planned or assigned trip can be assigned.');

            $driverId = (int) $data['driver_id'];
            $vehicleId = filled($data['vehicle_id'] ?? null) ? (int) $data['vehicle_id'] : null;
            $trailerId = filled($data['trailer_id'] ?? null) ? (int) $data['trailer_id'] : null;

            if ($vehicleId === null) {
                $defaults = $this->defaultsFor($driverId);
                $vehicleId = $defaults['vehicle_id'] ?? throw ValidationException::withMessages(['vehicle_id' => 'Choose a vehicle. This driver has no active asset group with an available vehicle.']);
                $trailerId ??= $defaults['trailer_id'];
            }

            $this->assertEligible($driverId, $vehicleId, $trailerId);

            $from = ['status' => $trip->status, 'driver_id' => $trip->driver_id, 'vehicle_id' => $trip->vehicle_id, 'trailer_id' => $trip->trailer_id];
            $trip->forceFill(['driver_id' => $driverId, 'vehicle_id' => $vehicleId, 'trailer_id' => $trailerId, 'status' => 'assigned']);

            if ($trip->isDirty(['driver_id', 'vehicle_id', 'trailer_id'])) {
                $trip->forceFill(['equipment_confirmed_at' => null, 'equipment_confirmed_by_user_id' => null]);
            }

            if ($trip->isDirty()) {
                $trip->save();
                AuditLog::record($actor, $trip->company_id, 'trip.assigned', $trip, [
                    'from' => $from,
                    'to' => ['driver_id' => $driverId, 'vehicle_id' => $vehicleId, 'trailer_id' => $trailerId],
                ]);
            }

            return $trip;
        });
    }

    /**
     * assigned → planned, without driver, equipment or confirmation.
     */
    public function unassign(Trip $trip, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $actor) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $this->assertStatus($trip, ['assigned'], 'Only an assigned trip can be unassigned.');
            $from = ['driver_id' => $trip->driver_id, 'vehicle_id' => $trip->vehicle_id, 'trailer_id' => $trip->trailer_id];

            $trip->forceFill([
                'status' => 'planned',
                'driver_id' => null,
                'vehicle_id' => null,
                'trailer_id' => null,
                'equipment_confirmed_at' => null,
                'equipment_confirmed_by_user_id' => null,
            ])->save();
            AuditLog::record($actor, $trip->company_id, 'trip.unassigned', $trip, ['from' => $from]);

            return $trip;
        });
    }

    /**
     * Req 4.6: the tractor and trailer are the right ones. Recorded by staff for the driver.
     */
    public function confirmEquipment(Trip $trip, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $actor) {
            $trip = Trip::query()->with(['vehicle:id,unit_no', 'trailer:id,unit_no'])->lockForUpdate()->findOrFail($trip->id);
            $this->assertStatus($trip, ['assigned'], 'Only the equipment of an assigned trip can be confirmed.');

            if ($trip->equipment_confirmed_at !== null) {
                throw ValidationException::withMessages(['trip' => 'The equipment of this trip is already confirmed.']);
            }

            $trip->forceFill(['equipment_confirmed_at' => now(), 'equipment_confirmed_by_user_id' => $actor->id])->save();
            AuditLog::record($actor, $trip->company_id, 'trip.equipment_confirmed', $trip, [
                'vehicle_id' => $trip->vehicle_id,
                'vehicle_unit_no' => $trip->vehicle?->unit_no,
                'trailer_id' => $trip->trailer_id,
                'trailer_unit_no' => $trip->trailer?->unit_no,
            ]);

            return $trip;
        });
    }

    /**
     * The vehicle and trailer of the driver's newest active asset group, each only while it can
     * still be assigned.
     *
     * @return array{vehicle_id: ?int, trailer_id: ?int}
     */
    public function defaultsFor(int $driverId): array
    {
        return $this->groupDefaults($driverId)[$driverId] ?? ['vehicle_id' => null, 'trailer_id' => null];
    }

    /**
     * Asset-group defaults per driver, for the page script (one query).
     *
     * @return array<int, array{vehicle_id: ?int, trailer_id: ?int}>
     */
    public function groupDefaults(?int $driverId = null): array
    {
        return AssetGroup::query()
            ->where('status', 'active')
            ->when($driverId !== null, fn ($q) => $q->where('driver_id', $driverId))
            ->with(['vehicle' => fn ($q) => $q->whereNull('transferred_out_at')->select('id'), 'trailer' => fn ($q) => $q->whereNull('transferred_out_at')->select('id')])
            ->orderByDesc('id')
            ->get(['id', 'driver_id', 'vehicle_id', 'trailer_id'])
            ->filter(fn (AssetGroup $group) => $group->vehicle !== null)
            ->unique('driver_id')
            ->mapWithKeys(fn (AssetGroup $group) => [$group->driver_id => ['vehicle_id' => $group->vehicle_id, 'trailer_id' => $group->trailer?->id]])
            ->all();
    }

    /**
     * Picker options: assignable drivers, vehicles and trailers, plus the trip's current picks.
     * A driver or vehicle already on another assigned or in-progress trip is marked, not hidden.
     *
     * @return array{drivers: array<int, string>, vehicles: array<int, string>, trailers: array<int, string>}
     */
    public function options(Trip $trip): array
    {
        $busy = $this->busyTrips($trip);
        $mark = fn (string $label, string $type, int $id) => isset($busy[$type][$id]) ? $label.' · on trip #'.$busy[$type][$id] : $label;

        // withTrashed so a current pick that was deleted still shows (and is refused on save)
        $drivers = Driver::query()->withTrashed()
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('employment_status', 'active')->whereNull('deleted_at'))->orWhere('id', $trip->driver_id))
            ->orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn (Driver $d) => [$d->id => $mark(trim($d->last_name.', '.$d->first_name, ', '), 'driver', $d->id)]);

        $assets = fn (string $model, ?int $current, string $type) => $model::query()->withTrashed()
            ->where(fn ($q) => $q->where(fn ($q) => $this->available($q)->whereNull('deleted_at'))->orWhere('id', $current))
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'make', 'model'])
            ->mapWithKeys(fn ($asset) => [$asset->id => $mark(trim($asset->unit_no.' · '.trim($asset->make.' '.$asset->model), ' ·'), $type, $asset->id)]);

        return [
            'drivers' => $drivers->all(),
            'vehicles' => $assets(Vehicle::class, $trip->vehicle_id, 'vehicle')->all(),
            'trailers' => $assets(Trailer::class, $trip->trailer_id, 'trailer')->all(),
        ];
    }

    /**
     * Things the dispatcher should know before the trip starts (not blocks): an expired or missing
     * CDL, an expired medical card ([L] 391.41, 391.51: the carrier decides), and other assigned or
     * in-progress trips of the same driver or vehicle. Expects driver.currentLicense and
     * driver.medicalCertificate loaded.
     *
     * @return list<string>
     */
    public function warnings(Trip $trip): array
    {
        if ($trip->driver === null) {
            return [];
        }

        $warnings = [];
        $today = Carbon::today($this->trips->timezone());
        $license = $trip->driver->currentLicense;
        $medical = $trip->driver->medicalCertificate;

        if ($license === null) {
            $warnings[] = 'The driver has no current license on file.';
        } elseif ($license->expires !== null && $license->expires->lt($today)) {
            $warnings[] = 'The driver\'s license expired on '.$license->expires->format('M d, Y').'.';
        }

        if ($medical?->expiry_date !== null && $medical->expiry_date->lt($today)) {
            $warnings[] = 'The driver\'s medical card expired on '.$medical->expiry_date->format('M d, Y').'.';
        }

        $busy = $this->busyTrips($trip);
        if (isset($busy['driver'][$trip->driver_id])) {
            $warnings[] = 'The driver is also on trip #'.$busy['driver'][$trip->driver_id].'.';
        }
        if ($trip->vehicle_id !== null && isset($busy['vehicle'][$trip->vehicle_id])) {
            $warnings[] = 'The vehicle is also on trip #'.$busy['vehicle'][$trip->vehicle_id].'.';
        }

        return $warnings;
    }

    /**
     * The same rules as the pickers, re-checked under the trip lock and again at start.
     */
    public function assertEligible(?int $driverId, ?int $vehicleId, ?int $trailerId): void
    {
        $errors = [];

        if ($driverId === null || ! Driver::query()->whereKey($driverId)->where('employment_status', 'active')->exists()) {
            $errors['driver_id'] = 'Choose an active driver. Applicants, inactive and terminated drivers can\'t be assigned.';
        }
        if ($vehicleId === null || ! $this->available(Vehicle::query())->whereKey($vehicleId)->exists()) {
            $errors['vehicle_id'] = 'Choose a vehicle that is not deleted or transferred out.';
        }
        if ($trailerId !== null && ! $this->available(Trailer::query())->whereKey($trailerId)->exists()) {
            $errors['trailer_id'] = 'Choose a trailer that is not deleted or transferred out.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Not deleted (the model scope) and not transferred out.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function available(Builder $query): Builder
    {
        return $query->whereNull('transferred_out_at');
    }

    /**
     * The trip number of another assigned or in-progress trip, per driver and per vehicle.
     *
     * @return array{driver: array<int, int>, vehicle: array<int, int>}
     */
    private function busyTrips(Trip $trip): array
    {
        if (isset($this->busy[$trip->id])) {
            return $this->busy[$trip->id];
        }
        $busy = ['driver' => [], 'vehicle' => []];

        Trip::query()->whereIn('status', ['assigned', 'in_progress'])->whereKeyNot($trip->id)
            ->orderBy('trip_number')
            ->get(['id', 'trip_number', 'driver_id', 'vehicle_id'])
            ->each(function (Trip $other) use (&$busy) {
                $busy['driver'][$other->driver_id] ??= $other->trip_number;
                $busy['vehicle'][$other->vehicle_id] ??= $other->trip_number;
            });

        return $this->busy[$trip->id] = $busy;
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
}

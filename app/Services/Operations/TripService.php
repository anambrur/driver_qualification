<?php

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\Shipper;
use App\Models\Trip;
use App\Models\TripNumberSequence;
use App\Models\User;
use App\Services\Documents\FolderService;
use App\Support\TimeZones;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Trips before assignment (req 4.1–4.3, 6.1, 6.3; lifecycle 10 §13). The number comes from the
 * company's trip_number_sequences row under a lock (company row first, 12 §3), can be changed to
 * another free number while the trip is a draft, and is locked once confirmed. A confirmed trip
 * with a start and a drop-off becomes planned on its own.
 */
class TripService
{
    private const DETAILS = ['shipper_id', 'product', 'weight', 'weight_unit', 'planned_distance_miles', 'notes'];

    private const STOP_COLUMNS = ['shipper_id', 'address_line', 'city', 'state_code', 'zip', 'country_code', 'latitude', 'longitude', 'place_source', 'place_ref'];

    public function __construct(private FolderService $folders, private TenantContext $tenant) {}

    /**
     * A draft with the next free number, its trip folder and its audit row, in one transaction.
     *
     * @param  array<string, mixed>  $data  validated TripRequest data
     */
    public function create(array $data, User $actor): Trip
    {
        return DB::transaction(function () use ($data, $actor) {
            $companyId = $this->tenant->requireCompanyId();
            $timezone = DB::table('companies')->where('id', $companyId)->lockForUpdate()->value('timezone');

            // Companies made by the provisioner already have the row
            DB::table('trip_number_sequences')->insertOrIgnore(['company_id' => $companyId, 'next_number' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $sequence = TripNumberSequence::query()->lockForUpdate()->findOrFail($companyId);
            $number = $this->firstFreeNumber($sequence->next_number);

            $this->assertShipper($data['shipper_id'] ?? null);

            $trip = new Trip(Arr::only($data, self::DETAILS));
            $trip->forceFill([
                'trip_number' => $number,
                'trip_date' => $data['trip_date'] ?? $this->today($timezone),
                'status' => 'draft',
                'created_by_user_id' => $actor->id,
            ])->save();

            $sequence->update(['next_number' => $number + 1]);
            $this->folders->ensureSystemFolder($trip);

            AuditLog::record($actor, $companyId, 'trip.created', $trip, ['trip_number' => $number, 'trip_date' => $trip->trip_date->toDateString()]);

            return $trip;
        });
    }

    /**
     * Today in the company's time zone (Z1): the default trip date.
     */
    public function today(?string $timezone = null): string
    {
        $timezone ??= DB::table('companies')->where('id', $this->tenant->requireCompanyId())->value('timezone');

        return CarbonImmutable::now($timezone ?: TimeZones::DEFAULT)->toDateString();
    }

    /**
     * Load, shipper, planned miles and notes; the number and date only while unconfirmed.
     *
     * @param  array<string, mixed>  $data  validated TripRequest data
     */
    public function updateDetails(Trip $trip, array $data, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $data, $actor) {
            // A typed number takes the company lock first, like create(), so the two never pick the same number
            if (($data['trip_number'] ?? null) !== null) {
                DB::table('companies')->where('id', $this->tenant->requireCompanyId())->lockForUpdate()->value('id');
            }

            $trip = $this->lock($trip);
            $this->assertStatus($trip, Trip::EDITABLE, 'Only a draft, planned or assigned trip can be edited.');
            $this->assertShipper($data['shipper_id'] ?? null, $trip->shipper_id);

            $trip->fill(Arr::only($data, self::DETAILS));
            $oldNumber = $trip->trip_number;

            foreach (['trip_number', 'trip_date'] as $field) {
                if (! array_key_exists($field, $data) || $data[$field] === null) {
                    continue;
                }

                $trip->{$field} = $data[$field];

                if ($trip->isDirty($field) && ($trip->isNumberLocked() || $trip->status !== 'draft')) {
                    throw ValidationException::withMessages([$field => 'The trip number and date are confirmed and can no longer change.']);
                }
            }

            if ($trip->isDirty('trip_number')) {
                $this->assertNumberFree((int) $trip->trip_number, $trip->id);
            }

            $this->unique(fn () => $trip->save());

            if ($oldNumber !== $trip->trip_number) {
                AuditLog::record($actor, $trip->company_id, 'trip.number_changed', $trip, ['from' => $oldNumber, 'to' => $trip->trip_number]);
            }

            return $trip;
        });
    }

    public function confirmNumber(Trip $trip, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $actor) {
            $trip = $this->lock($trip);

            if ($trip->isNumberLocked()) {
                throw ValidationException::withMessages(['trip' => 'The number and date of this trip are already confirmed.']);
            }
            $this->assertStatus($trip, ['draft'], 'Only a draft trip can have its number and date confirmed.');

            $trip->forceFill(['number_confirmed_at' => now(), 'number_confirmed_by_user_id' => $actor->id])->save();
            AuditLog::record($actor, $trip->company_id, 'trip.number_confirmed', $trip, ['trip_number' => $trip->trip_number, 'trip_date' => $trip->trip_date->toDateString()]);

            return $this->planIfReady($trip, $actor);
        });
    }

    /**
     * The planned route: a start, an optional pickup and a drop-off. Rows are kept per type, so
     * arrival and departure times survive an address change; sequence follows the route.
     *
     * @param  array{start: array<string, mixed>, pickup: array<string, mixed>|null, drop_off: array<string, mixed>}  $stops  validated TripStopsRequest data
     */
    public function saveStops(Trip $trip, array $stops, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $stops, $actor) {
            $trip = $this->lock($trip);
            $this->assertStatus($trip, Trip::EDITABLE, 'Only the stops of a draft, planned or assigned trip can be changed.');
            $existing = $trip->stops()->get()->keyBy('stop_type');

            if (($stops['start']['also_pickup'] ?? false) && $stops['pickup'] !== null) {
                throw ValidationException::withMessages(['stops.pickup' => 'The start is already the pickup. Remove the separate pickup or untick "Start is the pickup".']);
            }

            foreach ($stops as $type => $stop) {
                if ($stop !== null) {
                    $this->assertShipper($stop['shipper_id'] ?? null, $existing->get($type)?->shipper_id, "stops.{$type}.shipper_id");
                }
            }

            $wanted = array_filter($stops);
            $sequence = array_flip(array_keys($wanted)); // start 0, [pickup 1,] drop_off last

            foreach ($existing as $type => $stop) {
                if (! isset($wanted[$type])) {
                    $stop->delete();
                }
            }

            // Last stop first: a drop-off moving 2 → 3 frees 2 for a new pickup, and a removed
            // pickup has already freed 2 for a drop-off moving back
            foreach (array_reverse($wanted, true) as $type => $stop) {
                $trip->stops()->updateOrCreate(['stop_type' => $type], Arr::only($stop, self::STOP_COLUMNS) + [
                    'sequence' => $sequence[$type] + 1,
                    'also_pickup' => $type === 'start' && (bool) ($stop['also_pickup'] ?? false),
                    'shipper_id' => null,
                ]);
            }

            return $this->planIfReady($trip, $actor);
        });
    }

    public function cancel(Trip $trip, string $reason, User $actor): Trip
    {
        return DB::transaction(function () use ($trip, $reason, $actor) {
            $trip = $this->lock($trip);
            $this->assertStatus($trip, Trip::CANCELLABLE, 'Only a draft, planned or assigned trip can be cancelled.');
            $from = $trip->status;

            $trip->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            AuditLog::record($actor, $trip->company_id, 'trip.cancelled', $trip, ['from' => $from, 'reason' => $reason]);

            return $trip;
        });
    }

    /**
     * A soft delete (audited by the model). The number stays taken.
     */
    public function delete(Trip $trip): void
    {
        DB::transaction(function () use ($trip) {
            $trip = $this->lock($trip);
            $this->assertStatus($trip, Trip::DELETABLE, 'Only a draft or cancelled trip can be deleted. Cancel it first.');

            $trip->delete();
        });
    }

    /**
     * draft → planned once the number is confirmed and the route has a start and a drop-off.
     */
    private function planIfReady(Trip $trip, User $actor): Trip
    {
        $routed = $trip->stops()->whereIn('stop_type', ['start', 'drop_off'])->count() === 2;

        if ($trip->status === 'draft' && $trip->isNumberLocked() && $routed) {
            $trip->forceFill(['status' => 'planned'])->save();
            AuditLog::record($actor, $trip->company_id, 'trip.planned', $trip, ['from' => 'draft']);
        }

        return $trip;
    }

    /**
     * The sequence's number, or the next one after numbers already taken by an override
     * (trashed trips included: a number is never reused).
     */
    private function firstFreeNumber(int $next): int
    {
        $taken = Trip::withTrashed()->where('trip_number', '>=', $next)->orderBy('trip_number')->pluck('trip_number');

        foreach ($taken as $number) {
            if ($number > $next) {
                break;
            }
            $next = $number + 1;
        }

        return $next;
    }

    private function assertNumberFree(int $number, int $tripId): void
    {
        if (Trip::withTrashed()->where('trip_number', $number)->whereKeyNot($tripId)->exists()) {
            throw ValidationException::withMessages(['trip_number' => "Trip number {$number} is already used. Numbers of deleted trips are not reused."]);
        }
    }

    /**
     * New choices must be active shippers of the company; the one already chosen may stay.
     */
    private function assertShipper(mixed $shipperId, ?int $current = null, string $field = 'shipper_id'): void
    {
        if ($shipperId === null || (int) $shipperId === $current) {
            return;
        }

        if (! Shipper::query()->active()->whereKey((int) $shipperId)->exists()) {
            throw ValidationException::withMessages([$field => 'Choose an active shipper.']);
        }
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

    /**
     * A concurrent override that took the same number.
     *
     * @param  Closure(): mixed  $write
     */
    private function unique(Closure $write): void
    {
        try {
            $write();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['trip_number' => 'That trip number is already used.']);
        }
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\AuditsDeletes;
use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A trip with one load (08 §9, req 4.1–4.3, Q6). The number is unique per company, taken from
 * trip_number_sequences, and locked once confirmed. Lifecycle: 10 §13.
 *
 * @property int $trip_number
 * @property Carbon $trip_date
 * @property string $status
 * @property Carbon|null $number_confirmed_at
 * @property Carbon|null $equipment_confirmed_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class Trip extends Model
{
    use AuditsDeletes, BelongsToCompany, HasFactory, SoftDeletes;

    protected const AUDIT_NAME = 'trip';

    public const STATUSES = ['draft', 'planned', 'assigned', 'in_progress', 'completed', 'cancelled'];

    /** Details and stops can change; once in progress only the stop times do (P2-3). */
    public const EDITABLE = ['draft', 'planned', 'assigned'];

    /** A driver and equipment can be (re)assigned. */
    public const ASSIGNABLE = ['planned', 'assigned'];

    public const CANCELLABLE = ['draft', 'planned', 'assigned'];

    public const DELETABLE = ['draft', 'cancelled'];

    public const WEIGHT_UNITS = ['lb' => 'lb', 'kg' => 'kg'];

    public const MAX_NUMBER = 99_999_999;

    /**
     * The trip's pickup stop (or its start with also_pickup) has not departed yet (08 §9, the SQL
     * form of pickupStop() and isPickedUp()). A route with neither has nothing to pick up.
     */
    public const PICKUP_PENDING_SQL = 'EXISTS (SELECT 1 FROM trip_stops WHERE trip_stops.company_id = trips.company_id AND trip_stops.trip_id = trips.id'
        ." AND trip_stops.departed_at IS NULL AND (trip_stops.stop_type = 'pickup' OR (trip_stops.stop_type = 'start' AND trip_stops.also_pickup = 1)))";

    /** Trips that count as loads: on the road or about to be. */
    public const LOAD_STATUSES = ['assigned', 'in_progress'];

    /** The number, date and status change only through TripService. */
    protected $fillable = [
        'shipper_id',
        'product',
        'weight',
        'weight_unit',
        'planned_distance_miles',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'trip_number' => 'integer',
            'trip_date' => 'date',
            'number_confirmed_at' => 'datetime',
            'weight' => 'decimal:2',
            'load_pay' => 'decimal:2',
            'planned_distance_miles' => 'decimal:1',
            'actual_distance_miles' => 'decimal:1',
            'paid_miles' => 'decimal:1',
            'equipment_confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<TripStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class)->orderBy('sequence');
    }

    /**
     * A deleted shipper keeps showing on the trips that used it.
     *
     * @return BelongsTo<Shipper, $this>
     */
    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Driver, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Trailer, $this>
     */
    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function equipmentConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'equipment_confirmed_by_user_id');
    }

    /**
     * Pre-trip inspections of this trip (P2-4), newest first.
     *
     * @return HasMany<Inspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class)->latest('id');
    }

    /**
     * Events recorded during the trip (P2-5), voided ones included.
     *
     * @return HasMany<TripEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(TripEvent::class);
    }

    /**
     * The trip's standard folder ("Trip documents").
     *
     * @return HasOne<Folder, $this>
     */
    public function folder(): HasOne
    {
        return $this->hasOne(Folder::class)->where('system_key', Folder::BINDINGS['trip_id']);
    }

    public function isNumberLocked(): bool
    {
        return $this->number_confirmed_at !== null;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, self::CANCELLABLE, true);
    }

    public function canBeDeleted(): bool
    {
        return in_array($this->status, self::DELETABLE, true);
    }

    public function label(): string
    {
        return '#'.$this->trip_number;
    }

    public function stop(string $type): ?TripStop
    {
        return $this->stops->firstWhere('stop_type', $type);
    }

    /**
     * Where the load is picked up: the pickup stop, or the start when it is also the pickup
     * (08 §9 "pending pickup"). None when the route has neither.
     */
    public function pickupStop(): ?TripStop
    {
        $start = $this->stop('start');

        return $this->stop('pickup') ?? ($start?->also_pickup ? $start : null);
    }

    public function isPickedUp(): bool
    {
        return $this->pickupStop()?->departed_at !== null;
    }

    /**
     * Assigned or in-progress trips whose load is still to be picked up (req 9.2).
     *
     * @param  Builder<Trip>  $query
     */
    public function scopePendingPickup(Builder $query): void
    {
        $query->whereIn('status', self::LOAD_STATUSES)->whereRaw(self::PICKUP_PENDING_SQL);
    }

    public function isAssignable(): bool
    {
        return in_array($this->status, self::ASSIGNABLE, true);
    }
}

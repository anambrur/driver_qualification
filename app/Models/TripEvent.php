<?php

namespace App\Models;

use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * An event recorded during a trip (08 §9, req 7.1–7.6): an unplanned stop, a police stop, a weigh
 * station, an accident (the accident register, C9), a breakdown or a fuel stop. Driver and vehicle
 * are copied from the trip. Evidence is never deleted; it is voided with a reason.
 *
 * @property string $type
 * @property Carbon $occurred_at
 * @property bool|null $ticket_issued
 * @property bool|null $weight_ok
 * @property bool|null $hazmat_released
 * @property Carbon|null $voided_at
 */
class TripEvent extends Model
{
    use BelongsToCompany, HasFactory;

    public const TYPES = [
        'stop' => 'Stop',
        'police_stop' => 'Police stop',
        'weigh_station' => 'Weigh station',
        'accident' => 'Accident',
        'breakdown' => 'Breakdown',
        'fuel' => 'Fuel stop',
    ];

    /** Everything is written through TripEventService. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'ticket_issued' => 'boolean',
            'weight_ok' => 'boolean',
            'details' => 'array',
            'injuries_count' => 'integer',
            'fatalities_count' => 'integer',
            'hazmat_released' => 'boolean',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class)->withTrashed();
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
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * The fuel stop's purchase (type fuel only).
     *
     * @return HasOne<FuelPurchase, $this>
     */
    public function fuelPurchase(): HasOne
    {
        return $this->hasOne(FuelPurchase::class);
    }

    /**
     * Files filed in the trip folder with this event as subject (F18).
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->whereNull('voided_at');
    }
}

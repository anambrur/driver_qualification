<?php

namespace App\Models;

use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A fuel purchase (08 §9, req 7.6), written with its fuel trip event. "Done" = confirmed_at, set
 * once a receipt is uploaded. Voiding the event voids the purchase: live() leaves it out of totals.
 *
 * @property string $gallons
 * @property string $price_per_gallon
 * @property string $total_amount
 * @property Carbon $purchased_at
 * @property Carbon|null $confirmed_at
 */
class FuelPurchase extends Model
{
    use BelongsToCompany, HasFactory;

    public const CURRENCY = 'USD';

    /** A typed-in total further than this from gallons × price gets a warning (not a block). */
    public const TOTAL_TOLERANCE = 0.01;

    /** Everything is written through TripEventService. */
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'gallons' => 'decimal:3',
            'price_per_gallon' => 'decimal:3',
            'total_amount' => 'decimal:2',
            'odometer' => 'integer',
            'confirmed_at' => 'datetime',
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
     * @return BelongsTo<TripEvent, $this>
     */
    public function tripEvent(): BelongsTo
    {
        return $this->belongsTo(TripEvent::class);
    }

    /**
     * @return BelongsTo<FuelType, $this>
     */
    public function fuelType(): BelongsTo
    {
        return $this->belongsTo(FuelType::class);
    }

    /**
     * Receipts filed in the trip folder with this purchase as subject (F18).
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /**
     * Whether the typed-in total is off gallons × price by more than the tolerance.
     */
    public static function totalMismatch(float $gallons, float $pricePerGallon, float $total): bool
    {
        $expected = $gallons * $pricePerGallon;

        return abs($total - $expected) > max($expected, 0.01) * self::TOTAL_TOLERANCE;
    }

    /**
     * Purchases whose fuel event is not voided.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('trip_event_id')
            ->orWhereHas('tripEvent', fn (Builder $event) => $event->whereNull('voided_at')));
    }
}

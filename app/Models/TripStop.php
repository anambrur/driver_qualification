<?php

namespace App\Models;

use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A planned stop of a trip (08 §9, req 6.1, 6.3): the start (also the pickup when also_pickup),
 * an optional pickup, and the drop-off.
 *
 * @property string $stop_type
 * @property int $sequence
 * @property bool $also_pickup
 */
class TripStop extends Model
{
    use BelongsToCompany, HasFactory;

    public const TYPES = ['start' => 'Start', 'pickup' => 'Pickup', 'drop_off' => 'Drop-off'];

    protected $fillable = [
        'trip_id',
        'sequence',
        'stop_type',
        'also_pickup',
        'shipper_id',
        'address_line',
        'city',
        'state_code',
        'zip',
        'country_code',
        'latitude',
        'longitude',
        'place_source',
        'place_ref',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'also_pickup' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'planned_at' => 'datetime',
            'arrived_at' => 'datetime',
            'departed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<Shipper, $this>
     */
    public function shipper(): BelongsTo
    {
        return $this->belongsTo(Shipper::class)->withTrashed();
    }

    public function cityState(): string
    {
        return trim($this->city.', '.$this->state_code, ', ');
    }
}

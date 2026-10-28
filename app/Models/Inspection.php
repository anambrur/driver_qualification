<?php

namespace App\Models;

use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A pre-trip inspection (08 §9, req 5.1–5.3). Driver, vehicle and trailer are copied from the trip
 * when it starts; only an inspection whose crew still matches the trip counts for the gate.
 *
 * @property string $status
 * @property string|null $result
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $results_count
 * @property int|null $items_count
 */
class Inspection extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUSES = ['in_progress' => 'In progress', 'completed' => 'Completed', 'abandoned' => 'Abandoned'];

    public const RESULTS = ['no_defects' => 'No defects', 'defects_found' => 'Defects found'];

    public const MAX_ODOMETER = 9_999_999;

    /** Status, result and the crew change only through InspectionService. */
    protected $fillable = ['odometer', 'notes'];

    protected function casts(): array
    {
        return [
            'odometer' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<Trailer, $this>
     */
    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<InspectionTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }

    /**
     * @return HasMany<InspectionResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }

    /**
     * Photos and files for failed items, filed in the trip folder (subject inspection_id).
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Whether the trip still has the driver and equipment this inspection was started for.
     */
    public function matchesCrewOf(Trip $trip): bool
    {
        return (int) $this->driver_id === (int) $trip->driver_id
            && (int) $this->vehicle_id === (int) $trip->vehicle_id
            && (int) $this->trailer_id === (int) $trip->trailer_id;
    }

    /**
     * Answered results ÷ template items, as a whole percentage (req 5.3). Needs results_count, and
     * items_count on the inspection (InspectionService::withProgress) or on its loaded template.
     */
    public function progress(): int
    {
        $items = $this->itemsCount();

        return $items === 0 ? 0 : intdiv((int) $this->results_count * 100, $items);
    }

    public function itemsCount(): int
    {
        return (int) ($this->items_count ?? ($this->relationLoaded('template') ? $this->template->getAttribute('items_count') : 0));
    }
}

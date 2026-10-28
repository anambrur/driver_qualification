<?php

namespace App\Models;

use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A requested export (08 §12, 10 §9): queued, built by BuildExport, ready until expires_at,
 * then expired by exports:prune. parameters holds the scope: driver_id for a driver ZIP, trip_id
 * for a trip ZIP, trips = 1 for a company ZIP with its trips.
 *
 * @property array<string, mixed> $parameters
 * @property Carbon|null $expires_at
 */
class Export extends Model
{
    use BelongsToCompany, HasFactory;

    public const TYPE_LABELS = [
        'zip_driver' => 'Driver ZIP',
        'zip_trip' => 'Trip ZIP',
        'zip_company' => 'Company ZIP',
        'year_end_report' => 'Year-end report',
        'profit_loss_report' => 'Profit and loss',
        'payroll_summary' => 'Payroll summary',
        'accident_register' => 'Accident register',
    ];

    /** The ZIP types (req 2.5): driver and company since Phase 1, trip since P2-6. */
    public const ZIP_TYPES = ['zip_driver', 'zip_trip', 'zip_company'];

    public const PENDING = ['queued', 'running'];

    protected $fillable = [
        'company_id',
        'requested_by_user_id',
        'type',
        'parameters',
        'status',
        'file_id',
        'error',
        'started_at',
        'finished_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function driverId(): ?int
    {
        return isset($this->parameters['driver_id']) ? (int) $this->parameters['driver_id'] : null;
    }

    public function tripId(): ?int
    {
        return isset($this->parameters['trip_id']) ? (int) $this->parameters['trip_id'] : null;
    }

    public function withTrips(): bool
    {
        return ! empty($this->parameters['trips']);
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'ready' && $this->file_id !== null && $this->expires_at?->isFuture() === true;
    }
}

<?php

// app/Models/ServiceLog.php

namespace App\Models;

use App\Models\Concerns\AuditsDeletes;
use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceLog extends Model
{
    use AuditsDeletes, BelongsToCompany, HasFactory, SoftDeletes;

    protected const AUDIT_NAME = 'service_log';

    protected $table = 'service_logs';

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'maintenance_schedule_id',
        'service_date',
        'maintenance_notes',
        'odometer_at_service',
        'current_odometer',
        'engine_hours_at_service',
        'current_engine_hours',
        'total_cost',
        'status',
    ];

    protected $casts = [
        'service_date' => 'date',
        'total_cost' => 'decimal:2',
        'odometer_at_service' => 'integer',
        'current_odometer' => 'integer',
        'engine_hours_at_service' => 'integer',
        'current_engine_hours' => 'integer',
    ];

    // Relationships
    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsToMany<MaintenanceCategory, $this>
     */
    public function maintenanceCategories(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceCategory::class, 'service_log_category')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ServiceDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ServiceDocument::class);
    }

    // Accessors
    public function getFormattedTotalCostAttribute()
    {
        return '$'.number_format((float) $this->total_cost, 2);
    }

    public function getMileageDifferenceAttribute()
    {
        return $this->current_odometer - $this->odometer_at_service;
    }

    public function getEngineHoursDifferenceAttribute()
    {
        if ($this->engine_hours_at_service && $this->current_engine_hours) {
            return $this->current_engine_hours - $this->engine_hours_at_service;
        }

        return null;
    }

    public function getCategoriesListAttribute()
    {
        return $this->maintenanceCategories->pluck('name')->toArray();
    }

    public function getCategoriesTextAttribute()
    {
        return $this->maintenanceCategories->pluck('name')->implode(', ');
    }

    public function getCategoriesHtmlAttribute()
    {
        $html = '';
        foreach ($this->maintenanceCategories as $category) {
            $html .= '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs mr-1">'.e($category->name).'</span>';
        }

        return $html;
    }

    // Scopes
    public function scopeForVehicle($query, $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('service_date', [$startDate, $endDate]);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeWithCategory($query, $categoryId)
    {
        return $query->whereHas('maintenanceCategories', function ($q) use ($categoryId) {
            $q->where('maintenance_category_id', $categoryId);
        });
    }

    /**
     * A vehicle's current engine hours: the vehicle's engine_hours column (PO-05), or 0.
     */
    public static function currentEngineHoursFor(?int $vehicleId): int
    {
        if ($vehicleId === null) {
            return 0;
        }

        return (int) Vehicle::withTrashed()->whereKey($vehicleId)->value('engine_hours');
    }

    /**
     * Update the vehicle's odometer and engine hours from this log. A log only raises them,
     * except when it is corrected downwards and its old value was the vehicle's current one:
     * the vehicle then gets the highest value logged now (PO-22).
     */
    public function updateVehicleMetrics(?int $previousOdometer = null, ?int $previousEngineHours = null): void
    {
        $vehicle = $this->vehicle;

        if (! $vehicle) {
            return;
        }

        $vehicle->odometer = $this->correctedMetric($vehicle->odometer, $this->current_odometer, $previousOdometer, 'current_odometer');
        $vehicle->engine_hours = $this->correctedMetric($vehicle->engine_hours, $this->current_engine_hours, $previousEngineHours, 'current_engine_hours');

        if ($vehicle->isDirty(['odometer', 'engine_hours'])) {
            $vehicle->save();
        }
    }

    private function correctedMetric(?int $vehicleValue, ?int $logValue, ?int $previousLogValue, string $column): ?int
    {
        if ($logValue !== null && $logValue > (int) $vehicleValue) {
            return $logValue;
        }

        $loweredTheCurrentValue = $previousLogValue !== null
            && $vehicleValue !== null
            && $previousLogValue === $vehicleValue
            && ($logValue ?? 0) < $previousLogValue;

        if ($loweredTheCurrentValue) {
            return (int) static::where('vehicle_id', $this->vehicle_id)->max($column);
        }

        return $vehicleValue;
    }
}

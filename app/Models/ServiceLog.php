<?php
// app/Models/ServiceLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'service_logs';

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'service_date',
        'maintenance_notes',
        'odometer_at_service',
        'current_odometer',
        'engine_hours_at_service',
        'current_engine_hours',
        'total_cost',
        'status'
    ];

    protected $casts = [
        'service_date' => 'date',
        'total_cost' => 'decimal:2',
        'odometer_at_service' => 'integer',
        'current_odometer' => 'integer',
        'engine_hours_at_service' => 'integer',
        'current_engine_hours' => 'integer'
    ];

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function maintenanceCategories()
    {
        return $this->belongsToMany(MaintenanceCategory::class, 'service_log_category')
            ->withTimestamps();
    }

    public function documents()
    {
        return $this->hasMany(ServiceDocument::class);
    }

    // Accessors
    public function getFormattedTotalCostAttribute()
    {
        return '$' . number_format($this->total_cost, 2);
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
            $html .= '<span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs mr-1">' . $category->name . '</span>';
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
     * Update vehicle odometer
     */
    public function updateVehicleMetrics()
    {
        $vehicle = $this->vehicle;

        if ($this->current_odometer > $vehicle->odometer) {
            $vehicle->odometer = $this->current_odometer;
            $vehicle->save();
        }
    }
}

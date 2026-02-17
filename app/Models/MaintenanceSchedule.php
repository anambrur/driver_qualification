<?php
// app/Models/MaintenanceSchedule.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'maintenance_category_id',
        'title',
        'schedule_type',
        'interval_days',
        'interval_miles',
        'interval_hours',
        'last_due_date',
        'last_due_mileage',
        'last_due_hours',
        'next_due_date',
        'next_due_mileage',
        'next_due_hours',
        'description',
        'notes',
        'is_active',
        'status'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_due_date' => 'date',
        'next_due_date' => 'date',
        'interval_days' => 'integer',
        'interval_miles' => 'integer',
        'interval_hours' => 'integer',
        'last_due_mileage' => 'integer',
        'last_due_hours' => 'integer',
        'next_due_mileage' => 'integer',
        'next_due_hours' => 'integer'
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

    public function maintenanceCategory()
    {
        return $this->belongsTo(MaintenanceCategory::class);
    }

    public function serviceLogs()
    {
        return $this->hasMany(ServiceLog::class, 'maintenance_schedule_id');
    }

    // Accessors
    public function getScheduleTypeLabelAttribute()
    {
        $labels = [
            'date' => 'By Date',
            'mileage' => 'By Mileage',
            'engine_hours' => 'By Engine Hours'
        ];
        return $labels[$this->schedule_type] ?? $this->schedule_type;
    }

    public function getIntervalTextAttribute()
    {
        switch ($this->schedule_type) {
            case 'date':
                return $this->interval_days ? "Every {$this->interval_days} days" : 'Not set';
            case 'mileage':
                return $this->interval_miles ? "Every " . number_format($this->interval_miles) . " miles" : 'Not set';
            case 'engine_hours':
                return $this->interval_hours ? "Every " . number_format($this->interval_hours) . " hours" : 'Not set';
            default:
                return 'Not set';
        }
    }

    public function getNextDueTextAttribute()
    {
        $parts = [];

        if ($this->next_due_date) {
            $parts[] = 'Date: ' . $this->next_due_date->format('M d, Y');
        }
        if ($this->next_due_mileage) {
            $parts[] = 'Mileage: ' . number_format($this->next_due_mileage) . ' mi';
        }
        if ($this->next_due_hours) {
            $parts[] = 'Hours: ' . number_format($this->next_due_hours) . ' hrs';
        }

        return !empty($parts) ? implode(' | ', $parts) : 'Not scheduled';
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'active' => 'bg-green-100 text-green-800',
            'paused' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-blue-100 text-blue-800'
        ];

        $class = $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
        $text = ucfirst($this->status);

        return "<span class='px-2 py-1 rounded-full text-xs font-medium {$class}'>{$text}</span>";
    }

    public function getVehicleInfoAttribute()
    {
        if (!$this->vehicle) {
            return '<span class="text-gray-400">All Vehicles</span>';
        }

        return $this->vehicle->unit_no . ' - ' . $this->vehicle->year . ' ' . $this->vehicle->make . ' ' . $this->vehicle->model;
    }

    // Methods
    public function calculateNextDue()
    {
        $vehicle = $this->vehicle;

        switch ($this->schedule_type) {
            case 'date':
                if ($this->last_due_date && $this->interval_days) {
                    $this->next_due_date = $this->last_due_date->addDays($this->interval_days);
                } elseif ($this->interval_days) {
                    $this->next_due_date = now()->addDays($this->interval_days);
                }
                break;

            case 'mileage':
                if ($vehicle && $this->interval_miles) {
                    $currentMileage = $vehicle->odometer;
                    if ($this->last_due_mileage) {
                        $this->next_due_mileage = $this->last_due_mileage + $this->interval_miles;
                    } else {
                        $this->next_due_mileage = $currentMileage + $this->interval_miles;
                    }
                }
                break;

            case 'engine_hours':
                if ($vehicle && $this->interval_hours) {
                    // Assuming vehicle has engine_hours field
                    $currentHours = $vehicle->engine_hours ?? 0;
                    if ($this->last_due_hours) {
                        $this->next_due_hours = $this->last_due_hours + $this->interval_hours;
                    } else {
                        $this->next_due_hours = $currentHours + $this->interval_hours;
                    }
                }
                break;
        }

        return $this;
    }

    public function isDue()
    {
        $vehicle = $this->vehicle;

        switch ($this->schedule_type) {
            case 'date':
                return $this->next_due_date && now()->startOfDay()->gte($this->next_due_date);

            case 'mileage':
                return $vehicle && $this->next_due_mileage && $vehicle->odometer >= $this->next_due_mileage;

            case 'engine_hours':
                $currentHours = $vehicle->engine_hours ?? 0;
                return $this->next_due_hours && $currentHours >= $this->next_due_hours;

            default:
                return false;
        }
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_active', true);
    }

    public function scopeDue($query)
    {
        return $query->where(function ($q) {
            $q->whereDate('next_due_date', '<=', now())
                ->orWhere('next_due_mileage', '<=', function ($sub) {
                    $sub->select('odometer')->from('vehicles')->whereColumn('vehicles.id', 'maintenance_schedules.vehicle_id');
                });
        });
    }

    public function scopeForVehicle($query, $vehicleId)
    {
        return $query->where(function ($q) use ($vehicleId) {
            $q->where('vehicle_id', $vehicleId)
                ->orWhereNull('vehicle_id');
        });
    }

    public function scopeByType($query, $type)
    {
        return $query->where('schedule_type', $type);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('maintenance_category_id', $categoryId);
    }
}

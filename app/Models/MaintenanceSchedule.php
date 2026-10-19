<?php

// app/Models/MaintenanceSchedule.php

namespace App\Models;

use App\Models\Concerns\AuditsDeletes;
use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceSchedule extends Model
{
    use AuditsDeletes, BelongsToCompany, HasFactory, SoftDeletes;

    protected const AUDIT_NAME = 'maintenance_schedule';

    protected $fillable = [
        'company_id',
        'vehicle_id',
        'maintenance_category_id',
        'title',
        'schedule_type',
        'interval_days',
        'interval_miles',
        'interval_hours',
        'last_service_date',
        'last_service_mileage',
        'last_service_hours',
        'next_due_date',
        'next_due_mileage',
        'next_due_hours',
        'description',
        'notes',
        'status',
    ];

    protected $casts = [
        'last_service_date' => 'date',
        'next_due_date' => 'date',
        'interval_days' => 'integer',
        'interval_miles' => 'integer',
        'interval_hours' => 'integer',
        'last_service_mileage' => 'integer',
        'last_service_hours' => 'integer',
        'next_due_mileage' => 'integer',
        'next_due_hours' => 'integer',
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
     * @return BelongsTo<MaintenanceCategory, $this>
     */
    public function maintenanceCategory(): BelongsTo
    {
        return $this->belongsTo(MaintenanceCategory::class);
    }

    /**
     * @return HasMany<ServiceLog, $this>
     */
    public function serviceLogs(): HasMany
    {
        return $this->hasMany(ServiceLog::class, 'maintenance_schedule_id');
    }

    // Accessors
    public function getScheduleTypeLabelAttribute()
    {
        $labels = [
            'date' => 'By Date',
            'mileage' => 'By Mileage',
            'engine_hours' => 'By Engine Hours',
        ];

        return $labels[$this->schedule_type] ?? $this->schedule_type;
    }

    public function getIntervalTextAttribute()
    {
        switch ($this->schedule_type) {
            case 'date':
                return $this->interval_days ? "Every {$this->interval_days} days" : 'Not set';
            case 'mileage':
                return $this->interval_miles ? 'Every '.number_format($this->interval_miles).' miles' : 'Not set';
            case 'engine_hours':
                return $this->interval_hours ? 'Every '.number_format($this->interval_hours).' hours' : 'Not set';
            default:
                return 'Not set';
        }
    }

    public function getNextDueTextAttribute()
    {
        $parts = [];

        if ($this->next_due_date) {
            $parts[] = 'Date: '.$this->next_due_date->format('M d, Y');
        }
        if ($this->next_due_mileage) {
            $parts[] = 'Mileage: '.number_format($this->next_due_mileage).' mi';
        }
        if ($this->next_due_hours) {
            $parts[] = 'Hours: '.number_format($this->next_due_hours).' hrs';
        }

        return ! empty($parts) ? implode(' | ', $parts) : 'Not scheduled';
    }

    public function getStatusBadgeAttribute()
    {
        $badges = [
            'active' => 'bg-success/15 text-success',
            'paused' => 'bg-warning/15 text-warning',
            'completed' => 'bg-info/15 text-info',
        ];

        $class = $badges[$this->status] ?? 'bg-muted text-foreground';
        $text = e(ucfirst((string) $this->status));

        return "<span class='px-2 py-1 rounded-full text-xs font-medium {$class}'>{$text}</span>";
    }

    public function getVehicleInfoAttribute()
    {
        if (! $this->vehicle) {
            return '<span class="text-muted-foreground">All Vehicles</span>';
        }

        return e($this->vehicle->unit_no.' - '.$this->vehicle->year.' '.$this->vehicle->make.' '.$this->vehicle->model);
    }

    // Methods
    public function calculateNextDue()
    {
        $vehicle = $this->vehicle;

        // Only the current type's due value applies; drop any left over from a previous type
        foreach (['date' => 'next_due_date', 'mileage' => 'next_due_mileage', 'engine_hours' => 'next_due_hours'] as $type => $column) {
            if ($this->schedule_type !== $type) {
                $this->{$column} = null;
            }
        }

        switch ($this->schedule_type) {
            case 'date':
                if ($this->last_service_date && $this->interval_days) {
                    $this->next_due_date = $this->last_service_date->addDays($this->interval_days);
                } elseif ($this->interval_days) {
                    $this->next_due_date = now()->addDays($this->interval_days);
                }
                break;

            case 'mileage':
                if ($vehicle && $this->interval_miles) {
                    $currentMileage = $vehicle->odometer;
                    if ($this->last_service_mileage) {
                        $this->next_due_mileage = $this->last_service_mileage + $this->interval_miles;
                    } else {
                        $this->next_due_mileage = $currentMileage + $this->interval_miles;
                    }
                }
                break;

            case 'engine_hours':
                if ($vehicle && $this->interval_hours) {
                    $currentHours = $this->currentEngineHours();
                    if ($this->last_service_hours) {
                        $this->next_due_hours = $this->last_service_hours + $this->interval_hours;
                    } else {
                        $this->next_due_hours = $currentHours + $this->interval_hours;
                    }
                }
                break;
        }

        return $this;
    }

    /**
     * The vehicle's engine hours (vehicles.engine_hours, raised by service logs, PO-05).
     */
    public function currentEngineHours(): int
    {
        return (int) $this->vehicle?->engine_hours;
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
                return $vehicle && $this->next_due_hours && $this->currentEngineHours() >= $this->next_due_hours;

            default:
                return false;
        }
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
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

    public function scopeByType($query, $type)
    {
        return $query->where('schedule_type', $type);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('maintenance_category_id', $categoryId);
    }
}

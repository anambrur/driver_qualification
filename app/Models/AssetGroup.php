<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'group_name',
        'driver_id',
        'primary_driver_name',
        'primary_driver_phone',
        'primary_driver_email',
        'second_driver_name',
        'second_driver_phone',
        'second_driver_email',
        'vehicle_id',
        'trailer_id',
        'status'
    ];

    protected $casts = [
        'status' => 'string'
    ];

    protected static function booted(): void
    {
        // A group without an explicit company belongs to its vehicle's company
        static::creating(function (AssetGroup $group) {
            if ($group->company_id === null && $group->vehicle_id !== null) {
                $group->company_id = Vehicle::withTrashed()->whereKey($group->vehicle_id)->value('company_id');
            }
        });
    }

    // Relationships
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Trailer, $this> */
    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Trailer::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    // Scope for searching
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('group_name', 'like', "%{$search}%")
                ->orWhere('primary_driver_name', 'like', "%{$search}%")
                ->orWhere('primary_driver_email', 'like', "%{$search}%")
                ->orWhere('primary_driver_phone', 'like', "%{$search}%")
                ->orWhere('second_driver_name', 'like', "%{$search}%");
        });
    }

    // Status options
    public static function getStatusOptions()
    {
        return [
            'active' => 'Active',
            'inactive' => 'Inactive'
        ];
    }

    // Accessor for full asset group info
    public function getFullInfoAttribute()
    {
        $vehicle = $this->vehicle ? $this->vehicle->unit_no : 'No Vehicle';
        $trailer = $this->trailer ? $this->trailer->unit_no : 'No Trailer';

        return "{$this->group_name} - Vehicle: {$vehicle}, Trailer: {$trailer}";
    }
}

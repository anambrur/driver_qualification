<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
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

    // Relationships
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function trailer()
    {
        return $this->belongsTo(Trailer::class);
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

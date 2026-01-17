<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_no',
        'vin',
        'year',
        'make',
        'model',
        'vehicle_type_id',
        'owned_by',
        'color',
        'title_no',
        'tire_size',
        'odometer',
        'gvw',
        'vehicle_group_id',
        'fuel_type_id',
        'engine_type',
        'transmission',
        'suspension',
        'no_axles',
        'configuration',
        'wheel_base',
        'size_dimension'
    ];

    protected $casts = [
        'year' => 'integer',
        'odometer' => 'integer',
        'gvw' => 'integer',
        'no_axles' => 'integer',
        'wheel_base' => 'integer'
    ];

    // Relationships
    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function vehicleGroup()
    {
        return $this->belongsTo(VehicleGroup::class);
    }

    public function fuelType()
    {
        return $this->belongsTo(FuelType::class);
    }

    // Scope for searching
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('unit_no', 'like', "%{$search}%")
                ->orWhere('vin', 'like', "%{$search}%")
                ->orWhere('make', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%")
                ->orWhere('year', 'like', "%{$search}%");
        });
    }

    // Accessor for full vehicle name
    public function getFullNameAttribute()
    {
        return "{$this->year} {$this->make} {$this->model} ({$this->unit_no})";
    }

    // Configuration options
    public static function getConfigurationOptions()
    {
        return [
            'conventional' => 'Conventional',
            'cabover' => 'Cabover'
        ];
    }

    // Owned by options
    public static function getOwnedByOptions()
    {
        return [
            'company' => 'Company Owned',
            'lease' => 'Leased',
            'rental' => 'Rental'
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trailer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_no',
        'vin',
        'year',
        'make',
        'model',
        'equipment_types_id',
        'owned_by',
        'color',
        'title_no',
        'tire_size',
        'gvw',
        'vehicle_group_id',
        'notes'
    ];

    protected $casts = [
        'year' => 'integer',
        'gvw' => 'integer'
    ];

    // Relationships
    public function equipmentType()
    {
        return $this->belongsTo(EquipmentType::class, 'equipment_types_id');
    }

    public function vehicleGroup()
    {
        return $this->belongsTo(VehicleGroup::class);
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

    // Accessor for full trailer name
    public function getFullNameAttribute()
    {
        return "{$this->year} {$this->make} {$this->model} ({$this->unit_no})";
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

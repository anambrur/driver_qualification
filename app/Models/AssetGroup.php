<?php

namespace App\Models;

use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetGroup extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'group_name',
        'driver_id',
        'co_driver_id',
        'vehicle_id',
        'trailer_id',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

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
            'inactive' => 'Inactive',
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

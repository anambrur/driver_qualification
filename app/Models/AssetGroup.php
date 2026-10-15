<?php

namespace App\Models;

use App\Models\Concerns\AuditsDeletes;
use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetGroup extends Model
{
    use AuditsDeletes, BelongsToCompany, HasFactory, SoftDeletes;

    protected const AUDIT_NAME = 'asset_group';

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

    /**
     * The second driver of a team (team driving is still PENDING, 14 §3).
     *
     * @return BelongsTo<Driver, $this>
     */
    public function coDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'co_driver_id');
    }

    // Scope for searching: the group name, or a driver's name, email or phone
    public function scopeSearch($query, $search)
    {
        $driverMatches = fn ($d) => $d->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%")
            ->orWhere('main_phone', 'like', "%{$search}%"));

        return $query->where(function ($q) use ($search, $driverMatches) {
            $q->where('group_name', 'like', "%{$search}%")
                ->orWhereHas('driver', $driverMatches)
                ->orWhereHas('coDriver', $driverMatches);
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

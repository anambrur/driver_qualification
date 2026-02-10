<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'module',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get vehicle documents of this type
     */
    public function vehicleDocuments()
    {
        return $this->hasMany(VehicleDocument::class);
    }

    /**
     * Get trailer documents of this type
     */
    public function trailerDocuments()
    {
        return $this->hasMany(TrailerDocument::class);
    }

    /**
     * Scope a query to only include active document types.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope to get vehicle document types
     */
    public function scopeVehicle($query)
    {
        return $query->where('module', 'vehicle');
    }

    /**
     * Scope to get trailer document types
     */
    public function scopeTrailer($query)
    {
        return $query->where('module', 'trailer');
    }

    /**
     * Scope a query to filter by module.
     */
    public function scopeByModule($query, $module)
    {
        if ($module) {
            return $query->where('module', $module);
        }
        return $query;
    }

    /**
     * Get the available modules.
     */
    public static function getModules()
    {
        return [
            'driver' => 'Driver',
            'vehicle' => 'Vehicle',
            'trailer' => 'Trailer',
        ];
    }

    /**
     * Get module label.
     */
    public function getModuleLabelAttribute()
    {
        return self::getModules()[$this->module] ?? ucfirst($this->module);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute()
    {
        return $this->status ? 'Active' : 'Inactive';
    }
}

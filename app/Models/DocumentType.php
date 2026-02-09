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
     * Scope a query to only include active document types.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
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

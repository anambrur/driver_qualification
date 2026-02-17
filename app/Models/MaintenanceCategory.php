<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceCategory extends Model
{
    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function serviceLogs()
    {
        return $this->belongsToMany(ServiceLog::class, 'service_log_category');
    }

    // Scope for ordering
    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }
}

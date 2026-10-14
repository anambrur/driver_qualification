<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanFeature extends Model
{
    protected $primaryKey = null;

    public $incrementing = false;

    protected $fillable = [
        'plan_id',
        'feature_key',
        'enabled',
        'limit_value',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'limit_value' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}

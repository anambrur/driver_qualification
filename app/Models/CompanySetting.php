<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    protected $primaryKey = 'company_id';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'week_starts_on' => 'integer',
        'overtime_daily_after_minutes' => 'integer',
        'double_overtime_daily_after_minutes' => 'integer',
        'overtime_weekly_after_minutes' => 'integer',
        'overtime_multiplier' => 'decimal:2',
        'double_overtime_multiplier' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

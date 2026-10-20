<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "Schedule a demo" request from the homepage, worked by platform staff.
 */
class DemoRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'company',
        'phone',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('handled_at');
    }

    public function scopeHandled(Builder $query): Builder
    {
        return $query->whereNotNull('handled_at');
    }

    /**
     * @return BelongsTo<PlatformUser, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'handled_by');
    }
}

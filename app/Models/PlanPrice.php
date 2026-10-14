<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable price row. A price change archives the row and inserts a new one.
 */
class PlanPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'interval',
        'amount',
        'currency',
        'stripe_price_id',
        'archived_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'archived_at' => 'datetime',
    ];

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function amountInCents(): int
    {
        return (int) round(((float) $this->amount) * 100);
    }
}

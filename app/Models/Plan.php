<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'currency',
        'billing_cycle', 'duration_days', 'trial_days',
        'is_active', 'is_featured', 'max_users', 'features', 'sort_order',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'is_active'  => 'boolean',
        'is_featured'=> 'boolean',
        'features'   => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getFormattedPriceAttribute(): string
    {
        if ($this->price == 0) {
            return 'Free';
        }

        return number_format($this->price, 2) . ' ' . $this->currency;
    }

    public function isFree(): bool
    {
        return $this->price == 0;
    }

    public function isLifetime(): bool
    {
        return $this->billing_cycle === 'lifetime';
    }

    public function isTrial(): bool
    {
        return $this->billing_cycle === 'trial';
    }
}

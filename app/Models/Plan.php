<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public const CYCLES = ['monthly', 'yearly', 'trial'];

    protected $fillable = [
        'name',
        'stripe_plan_id',
        'stripe_price_id',
        'slug',
        'description',
        'price',
        'currency',
        'billing_cycle',
        'duration_days',
        'trial_days',
        'is_active',
        'is_featured',
        'sort_order',
        'features',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'price' => 'decimal:2',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    public function isTrial(): bool
    {
        return $this->billing_cycle === 'trial';
    }

    public function isMonthly(): bool
    {
        return $this->billing_cycle === 'monthly';
    }

    public function isYearly(): bool
    {
        return $this->billing_cycle === 'yearly';
    }

    public function stripeInterval(): ?string
    {
        return match ($this->billing_cycle) {
            'monthly' => 'month',
            'yearly' => 'year',
            default => null,
        };
    }

    public function amountInCents(): int
    {
        return (int) round(((float) $this->price) * 100);
    }
}

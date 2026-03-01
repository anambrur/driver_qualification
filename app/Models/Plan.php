<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

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
}

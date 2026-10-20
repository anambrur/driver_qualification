<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_public',
        'is_active',
        'is_featured',
        'sort_order',
        'marketing_bullets',
    ];

    protected $casts = [
        'marketing_bullets' => 'array',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Public, active plans with their current prices, for pricing pages.
     */
    public function scopeForSale(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_public', true)->orderBy('sort_order')->orderBy('id')
            ->whereHas('prices', fn (Builder $q) => $q->whereNull('archived_at'))
            ->with(['prices' => fn ($q) => $q->whereNull('archived_at')->orderBy('interval')]);
    }

    public function currentPrice(string $interval = 'month'): ?PlanPrice
    {
        return $this->prices->first(fn (PlanPrice $price) => $price->interval === $interval && $price->archived_at === null);
    }

    /**
     * @return HasMany<PlanPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class);
    }

    /**
     * @return HasMany<PlanFeature, $this>
     */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }
}

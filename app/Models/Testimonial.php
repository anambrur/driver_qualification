<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * A customer quote on the homepage. Platform-level content, not tenant data.
 */
class Testimonial extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'landing.testimonials';

    protected $fillable = [
        'name',
        'role',
        'company',
        'quote',
        'rating',
        'avatar',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Active testimonials for the homepage, cached until one changes.
     *
     * @return Collection<int, self>
     */
    public static function forHomepage(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->active()->ordered()->get());
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * A question and answer on the homepage. Platform-level content, not tenant data.
 */
class Faq extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'landing.faqs';

    protected $fillable = [
        'question',
        'answer',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
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
     * Active questions for the homepage, cached until one changes.
     *
     * @return Collection<int, self>
     */
    public static function forHomepage(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->active()->ordered()->get());
    }
}

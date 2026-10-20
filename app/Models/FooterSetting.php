<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * The saved public footer (one row); App\Support\Footer merges it over the defaults.
 *
 * @property array<string, mixed>|null $content
 */
class FooterSetting extends Model
{
    public const CACHE_KEY = 'footer_settings';

    protected $fillable = [
        'content',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * The saved footer sections, cached until saved again.
     *
     * @return array<string, mixed>
     */
    public static function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->first()->content ?? []);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_name',
        'logo',
        'favicon',
        'email',
        'phone',
        'address',
        'facebook_url',
        'twitter_url',
        'linkedin_url',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'google_analytics_id',
        'tawk_enabled',
        'tawk_property_id',
        'tawk_widget_id',
        'tawk_widget_code',
    ];

    protected function casts(): array
    {
        return [
            'tawk_enabled' => 'boolean',
            'tawk_widget_code' => 'encrypted',
        ];
    }

    /**
     * The "booted" method of the model.
     * Automatically clear the settings cache when updated.
     */
    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('site_settings');
        });

        static::deleted(function () {
            Cache::forget('site_settings');
        });
    }

    /**
     * Get the global settings (using cache).
     */
    public static function getSettings()
    {
        return Cache::rememberForever('site_settings', function () {
            return self::first() ?? self::create(['site_name' => 'My Application']);
        });
    }
}

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
        'meta_title',
        'meta_description',
        'meta_keywords',
        'google_analytics_id',
        'tawk_enabled',
        'tawk_property_id',
        'tawk_widget_id',
        'tawk_widget_code',
        'default_alcohol_drug_test_policy_pdf',
        'default_general_work_policy_pdf',
        'trial_plan_id',
        'trial_days',
        'grace_days',
        'support_session_max_minutes',
        'legal_name',
        'support_email',
        'support_phone',
        'timezone',
        'primary_color',
        'accent_color',
        'default_color_mode',
        'logo_dark',
        'email_logo',
        'og_image',
        'twitter_card',
        'canonical_base_url',
        'robots_noindex_default',
        'search_console_verification',
        'mail_from_name',
        'mail_from_address',
        'landing',
    ];

    protected function casts(): array
    {
        return [
            'tawk_enabled' => 'boolean',
            'robots_noindex_default' => 'boolean',
            'trial_days' => 'integer',
            'grace_days' => 'integer',
            'support_session_max_minutes' => 'integer',
            'tawk_widget_code' => 'encrypted',
            'landing' => 'array',
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

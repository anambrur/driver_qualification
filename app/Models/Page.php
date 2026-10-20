<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A public site page (About, Careers, legal pages…). The set is fixed: platform staff edit
 * pages but never add or remove them. The `blog` row holds the blog index heading and intro.
 */
class Page extends Model
{
    public const CACHE_KEY = 'site.pages.published';

    /** slug => default title */
    public const SLUGS = [
        'about' => 'About Us',
        'blog' => 'Blog',
        'careers' => 'Careers',
        'contact' => 'Contact',
        'security' => 'Security',
        'privacy-policy' => 'Privacy Policy',
        'terms-of-service' => 'Terms of Service',
    ];

    /** Pages that show a "Last updated" date. */
    public const LEGAL = ['security', 'privacy-policy', 'terms-of-service'];

    protected $fillable = [
        'slug',
        'title',
        'summary',
        'body',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isLegal(): bool
    {
        return in_array($this->slug, self::LEGAL, true);
    }

    /** A seeded legal template nobody has edited yet. */
    public function isUnreviewedTemplate(): bool
    {
        return $this->isLegal() && $this->updated_at?->equalTo($this->created_at);
    }

    public function url(): string
    {
        return self::urlFor($this->slug);
    }

    public static function urlFor(string $slug): string
    {
        return $slug === 'blog' ? route('blog.index') : route('pages.show', $slug);
    }

    /**
     * Titles of the published pages, by slug; cached until a page changes.
     *
     * @return array<string, string>
     */
    public static function published(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()
            ->where('is_published', true)
            ->pluck('title', 'slug')
            ->all());
    }

    /** The URL of a published page, or null. */
    public static function publishedUrl(string $slug): ?string
    {
        return isset(self::published()[$slug]) ? self::urlFor($slug) : null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A public blog post written by platform staff. A null or future published_at is not public.
 *
 * @property Carbon|null $published_at
 */
class Post extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'meta_description',
        'cover_image',
        'body',
        'published_at',
        'author_id',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * @return BelongsTo<PlatformUser, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'author_id');
    }
}

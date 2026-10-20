<?php

namespace App\Services\Platform;

use App\Models\AuditLog;
use App\Models\FooterSetting;
use App\Models\Page;
use App\Models\PlatformUser;
use App\Models\Post;
use App\Support\RichText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The public footer, site pages and blog posts. Editor HTML is sanitized here, on save, and every
 * change by platform staff is audited.
 */
class SitePageService
{
    /**
     * @param  array<string, mixed>  $footer
     */
    public function updateFooter(PlatformUser $actor, array $footer): void
    {
        DB::transaction(function () use ($actor, $footer) {
            $setting = FooterSetting::query()->first() ?? new FooterSetting;
            $setting->content = $footer;
            $setting->save();
            AuditLog::record($actor, null, 'footer.updated', $setting, ['sections' => array_keys($footer)]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updatePage(PlatformUser $actor, Page $page, array $attributes): void
    {
        $page->fill(['body' => RichText::clean($attributes['body'] ?? null)] + $attributes);
        $changes = array_keys($page->getDirty());

        // Saving unchanged still counts as a review of a seeded template
        DB::transaction(function () use ($actor, $page, $changes) {
            $page->touch();
            AuditLog::record($actor, null, 'page.updated', $page, ['slug' => $page->slug, 'fields' => $changes]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPost(PlatformUser $actor, array $attributes, ?UploadedFile $cover): Post
    {
        $stored = $cover?->storeAs('blog', $cover->hashName(), 'public');

        return DB::transaction(function () use ($actor, $attributes, $stored) {
            $post = Post::query()->create([
                'body' => RichText::clean($attributes['body'] ?? null),
                'cover_image' => $stored ?: null,
                'author_id' => $actor->getKey(),
            ] + $attributes);
            AuditLog::record($actor, null, 'post.created', $post, ['title' => $post->title]);

            return $post;
        });
    }

    /**
     * A new cover, or $removeCover, replaces the old file; the old file goes once the row is saved.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updatePost(PlatformUser $actor, Post $post, array $attributes, ?UploadedFile $cover, bool $removeCover = false): void
    {
        $old = $post->cover_image;

        if ($cover) {
            $attributes['cover_image'] = $cover->storeAs('blog', $cover->hashName(), 'public') ?: null;
        } elseif ($removeCover) {
            $attributes['cover_image'] = null;
        }

        $post->fill(['body' => RichText::clean($attributes['body'] ?? null)] + $attributes);
        $changes = array_keys($post->getDirty());

        if ($changes === []) {
            return;
        }

        DB::transaction(function () use ($actor, $post, $changes) {
            $post->save();
            AuditLog::record($actor, null, 'post.updated', $post, ['fields' => $changes]);
        });

        if ($old && $old !== $post->cover_image) {
            Storage::disk('public')->delete($old);
        }
    }

    public function deletePost(PlatformUser $actor, Post $post): void
    {
        DB::transaction(function () use ($actor, $post) {
            $post->delete();
            AuditLog::record($actor, null, 'post.deleted', $post, ['title' => $post->title]);
        });

        if ($post->cover_image) {
            Storage::disk('public')->delete($post->cover_image);
        }
    }
}

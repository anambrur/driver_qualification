<?php

namespace App\Services\Platform;

use App\Models\AuditLog;
use App\Models\DemoRequest;
use App\Models\Faq;
use App\Models\PlatformUser;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Public homepage content: the landing page copy, testimonials, FAQs and demo requests. Every
 * change by platform staff is audited.
 */
class SiteContentService
{
    /**
     * @param  array<string, mixed>  $landing
     */
    public function updateLanding(PlatformUser $actor, array $landing): void
    {
        $setting = SiteSetting::getSettings();

        DB::transaction(function () use ($actor, $setting, $landing) {
            $setting->update(['landing' => $landing]);
            AuditLog::record($actor, null, 'landing.updated', $setting, ['sections' => array_keys($landing)]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTestimonial(PlatformUser $actor, array $attributes, ?UploadedFile $avatar): Testimonial
    {
        $stored = $avatar?->storeAs('testimonials', $avatar->hashName(), 'public');

        return DB::transaction(function () use ($actor, $attributes, $stored) {
            $testimonial = Testimonial::query()->create($attributes + ['avatar' => $stored ?: null]);
            AuditLog::record($actor, null, 'testimonial.created', $testimonial, ['name' => $testimonial->name]);

            return $testimonial;
        });
    }

    /**
     * A new avatar, or $removeAvatar, replaces the old file; the old file goes once the row is saved.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateTestimonial(PlatformUser $actor, Testimonial $testimonial, array $attributes, ?UploadedFile $avatar, bool $removeAvatar = false): void
    {
        $old = $testimonial->avatar;

        if ($avatar) {
            $attributes['avatar'] = $avatar->storeAs('testimonials', $avatar->hashName(), 'public') ?: null;
        } elseif ($removeAvatar) {
            $attributes['avatar'] = null;
        }

        $testimonial->fill($attributes);
        $changes = $testimonial->getDirty();

        if ($changes === []) {
            return;
        }

        DB::transaction(function () use ($actor, $testimonial, $changes) {
            $testimonial->save();
            AuditLog::record($actor, null, 'testimonial.updated', $testimonial, $changes);
        });

        if ($old && $old !== $testimonial->avatar) {
            Storage::disk('public')->delete($old);
        }
    }

    public function deleteTestimonial(PlatformUser $actor, Testimonial $testimonial): void
    {
        DB::transaction(function () use ($actor, $testimonial) {
            $testimonial->delete();
            AuditLog::record($actor, null, 'testimonial.deleted', $testimonial, ['name' => $testimonial->name]);
        });

        if ($testimonial->avatar) {
            Storage::disk('public')->delete($testimonial->avatar);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFaq(PlatformUser $actor, array $attributes): Faq
    {
        return DB::transaction(function () use ($actor, $attributes) {
            $faq = Faq::query()->create($attributes);
            AuditLog::record($actor, null, 'faq.created', $faq, ['question' => $faq->question]);

            return $faq;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateFaq(PlatformUser $actor, Faq $faq, array $attributes): void
    {
        $faq->fill($attributes);
        $changes = $faq->getDirty();

        if ($changes === []) {
            return;
        }

        DB::transaction(function () use ($actor, $faq, $changes) {
            $faq->save();
            AuditLog::record($actor, null, 'faq.updated', $faq, $changes);
        });
    }

    public function deleteFaq(PlatformUser $actor, Faq $faq): void
    {
        DB::transaction(function () use ($actor, $faq) {
            $faq->delete();
            AuditLog::record($actor, null, 'faq.deleted', $faq, ['question' => $faq->question]);
        });
    }

    public function markDemoRequestHandled(PlatformUser $actor, DemoRequest $demoRequest): void
    {
        if ($demoRequest->handled_at !== null) {
            return;
        }

        DB::transaction(function () use ($actor, $demoRequest) {
            $demoRequest->forceFill(['handled_at' => now(), 'handled_by' => $actor->getKey()])->save();
            AuditLog::record($actor, null, 'demo_request.handled', $demoRequest);
        });
    }

    public function deleteDemoRequest(PlatformUser $actor, DemoRequest $demoRequest): void
    {
        DB::transaction(function () use ($actor, $demoRequest) {
            $demoRequest->delete();
            AuditLog::record($actor, null, 'demo_request.deleted', $demoRequest);
        });
    }
}

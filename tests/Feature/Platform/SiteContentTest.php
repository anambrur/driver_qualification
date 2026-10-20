<?php

use App\Models\AuditLog;
use App\Models\Faq;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Support\Landing;
use Database\Factories\FaqFactory;
use Database\Factories\TestimonialFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;

/*
| Platform > Content: homepage testimonials, FAQs and landing page copy. Platform staff with
| platform.content.manage only; every change is audited.
*/

describe('testimonials', function () {
    it('adds a testimonial with a photo and audits it', function () {
        Storage::fake('public');

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->post(route('platform.content.testimonials.store'), [
                'name' => 'Dana Ruiz', 'role' => 'Safety Director', 'company' => 'Ruiz Freight', 'quote' => 'Audit took an hour.',
                'rating' => 4, 'is_active' => '1', 'sort_order' => 2, 'avatar' => UploadedFile::fake()->image('dana.jpg'),
            ])
            ->assertRedirect(route('platform.content.testimonials.index'))
            ->assertSessionHasNoErrors();

        $testimonial = Testimonial::query()->sole();
        expect([$testimonial->name, $testimonial->rating, $testimonial->sort_order, $testimonial->is_active])->toBe(['Dana Ruiz', 4, 2, true])
            ->and($testimonial->avatar)->toStartWith('testimonials/')
            ->and(AuditLog::where('action', 'testimonial.created')->count())->toBe(1);
        Storage::disk('public')->assertExists($testimonial->avatar);
    });

    it('rejects an svg photo and a rating outside 1 to 5', function () {
        $this->actingAs(Actors::platformOwner(), 'platform')
            ->post(route('platform.content.testimonials.store'), [
                'name' => 'X', 'quote' => 'Y', 'rating' => 6,
                'avatar' => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            ])
            ->assertSessionHasErrors(['rating', 'avatar']);

        expect(Testimonial::query()->count())->toBe(0);
    });

    it('replaces and removes the photo, deleting the old file', function () {
        Storage::fake('public');
        $owner = Actors::platformOwner();
        $testimonial = TestimonialFactory::new()->create(['avatar' => UploadedFile::fake()->image('old.jpg')->store('testimonials', 'public')]);
        $old = $testimonial->avatar;
        $fields = ['name' => $testimonial->name, 'quote' => $testimonial->quote, 'rating' => 5, 'is_active' => '1'];

        $this->actingAs($owner, 'platform')
            ->put(route('platform.content.testimonials.update', $testimonial), $fields + ['avatar' => UploadedFile::fake()->image('new.jpg')])
            ->assertSessionHasNoErrors();
        $new = $testimonial->fresh()->avatar;
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);

        $this->put(route('platform.content.testimonials.update', $testimonial), $fields + ['remove_avatar' => '1'])->assertSessionHasNoErrors();
        expect($testimonial->fresh()->avatar)->toBeNull();
        Storage::disk('public')->assertMissing($new);
    });

    it('deletes a testimonial and audits it', function () {
        $testimonial = TestimonialFactory::new()->create();

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->delete(route('platform.content.testimonials.destroy', $testimonial))
            ->assertRedirect(route('platform.content.testimonials.index'));

        expect(Testimonial::query()->count())->toBe(0)
            ->and(AuditLog::where('action', 'testimonial.deleted')->count())->toBe(1);
    });
});

describe('faqs', function () {
    it('adds, updates and deletes a question with an audit row each', function () {
        $this->actingAs(Actors::platformOwner(), 'platform')
            ->post(route('platform.content.faqs.store'), ['question' => 'Do you support ELDs?', 'answer' => 'Not yet.', 'is_active' => '1'])
            ->assertRedirect(route('platform.content.faqs.index'));
        $faq = Faq::query()->sole();

        $this->put(route('platform.content.faqs.update', $faq), ['question' => 'Do you support ELDs?', 'answer' => 'Coming soon.', 'sort_order' => 3])
            ->assertSessionHasNoErrors();
        expect([$faq->fresh()->answer, $faq->fresh()->is_active, $faq->fresh()->sort_order])->toBe(['Coming soon.', false, 3]);

        $this->delete(route('platform.content.faqs.destroy', $faq));

        expect(Faq::query()->count())->toBe(0)
            ->and(AuditLog::whereIn('action', ['faq.created', 'faq.updated', 'faq.deleted'])->count())->toBe(3);
    });

    it('requires a question and an answer', function () {
        $this->actingAs(Actors::platformOwner(), 'platform')
            ->post(route('platform.content.faqs.store'), ['question' => '', 'answer' => ''])
            ->assertSessionHasErrors(['question', 'answer']);
    });

    it('keeps content screens from staff without platform.content.manage', function () {
        $faq = FaqFactory::new()->create();

        $this->actingAs(Actors::platformStaff('billing_manager'), 'platform')
            ->put(route('platform.content.faqs.update', $faq), ['question' => 'Hacked?', 'answer' => 'Yes'])
            ->assertForbidden();

        expect($faq->fresh()->question)->not->toBe('Hacked?');
    });
});

describe('landing page', function () {
    function landingInput(array $overrides = []): array
    {
        return array_replace_recursive(Landing::defaults(), [
            'stats' => ['enabled' => '1'], 'features' => ['enabled' => '1'], 'steps' => ['enabled' => '1'],
            'pricing' => ['enabled' => '1'], 'testimonials' => ['enabled' => '1'], 'faq' => ['enabled' => '1'],
            'cta' => ['enabled' => '1', 'demo' => '1'],
        ], $overrides);
    }

    it('saves the landing page, dropping blank rows, and audits it', function () {
        $input = landingInput(['hero' => ['title' => 'Compliance, done.'], 'faq' => ['enabled' => '0']]);
        $input['stats']['items'] = [['value' => '1,000+', 'label' => 'Fleets'], ['value' => '', 'label' => '']];

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->put(route('platform.content.landing.update'), $input)
            ->assertSessionHasNoErrors();

        $landing = SiteSetting::query()->sole()->landing;
        expect($landing['hero']['title'])->toBe('Compliance, done.')
            ->and($landing['stats']['items'])->toEqual([['value' => '1,000+', 'label' => 'Fleets']])
            ->and($landing['faq']['enabled'])->toBeFalse()
            ->and(Landing::content()['hero']['title'])->toBe('Compliance, done.')
            ->and(AuditLog::where('action', 'landing.updated')->count())->toBe(1);
    });

    it('rejects an unknown icon and too many stats', function () {
        $input = landingInput();
        $input['features']['items'][0]['icon'] = 'not-an-icon';
        $input['stats']['items'] = array_fill(0, 5, ['value' => '1', 'label' => 'x']);

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->put(route('platform.content.landing.update'), $input)
            ->assertSessionHasErrors(['features.items.0.icon', 'stats.items']);

        expect(SiteSetting::query()->value('landing'))->toBeNull();
    });

    it('falls back to the defaults for sections never saved', function () {
        SiteSetting::getSettings()->update(['landing' => ['cta' => ['title' => 'Go']]]);

        $content = Landing::content();

        expect($content['cta']['title'])->toBe('Go')
            ->and($content['cta']['enabled'])->toBeTrue()
            ->and($content['features'])->toEqual(Landing::defaults()['features']);
    });
});

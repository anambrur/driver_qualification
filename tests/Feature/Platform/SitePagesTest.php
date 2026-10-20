<?php

use App\Mail\DemoRequestReceivedMail;
use App\Models\AuditLog;
use App\Models\DemoRequest;
use App\Models\FooterSetting;
use App\Models\Page;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Support\Footer;
use App\Support\RichText;
use Database\Seeders\SitePagesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;

/*
| Public site pages, blog and footer (Platform > Content). Pages are a fixed, editable set;
| footer links only point at pages or homepage sections; editor HTML is sanitized on save.
*/

beforeEach(function () {
    $this->seed(SitePagesSeeder::class);
});

describe('seeder', function () {
    it('creates every page, three posts and the footer once, keeping edits', function () {
        Page::query()->where('slug', 'about')->update(['title' => 'Our story']);

        $this->seed(SitePagesSeeder::class);

        expect(Page::query()->count())->toBe(count(Page::SLUGS))
            ->and(Page::query()->where('slug', 'about')->value('title'))->toBe('Our story')
            ->and(Post::query()->published()->count())->toBe(3)
            ->and(FooterSetting::query()->count())->toBe(1);
    });

    it('marks seeded legal pages as templates until saved', function () {
        $page = Page::query()->where('slug', 'privacy-policy')->sole();
        expect($page->isUnreviewedTemplate())->toBeTrue();

        $this->travel(1)->minutes();
        $this->actingAs(Actors::platformOwner(), 'platform')
            ->put(route('platform.content.pages.update', $page), ['title' => $page->title, 'body' => $page->body, 'is_published' => '1'])
            ->assertSessionHasNoErrors();

        expect($page->fresh()->isUnreviewedTemplate())->toBeFalse();
    });
});

describe('public pages', function () {
    it('shows every published page with the shared footer', function (string $slug) {
        $page = Page::query()->where('slug', $slug)->sole();

        $this->get($page->url())->assertOk()
            ->assertSee('<h1', false)
            ->assertSee($page->title)
            ->assertSee('aria-label="Footer"', false)
            ->assertSee('href="'.route('pages.show', 'privacy-policy').'"', false);
    })->with(['about', 'careers', 'contact', 'security', 'privacy-policy', 'terms-of-service']);

    it('returns 404 for a hidden page and drops it from the footer', function () {
        Page::query()->where('slug', 'careers')->sole()->update(['is_published' => false]);

        $this->get('/careers')->assertNotFound();
        $this->get('/about')->assertOk()->assertDontSee('href="'.route('pages.show', 'careers').'"', false);
    });

    it('shows "last updated" on legal pages only', function () {
        $this->get('/terms-of-service')->assertSee('Last updated');
        $this->get('/about')->assertDontSee('Last updated');
    });

    it('lets search engines index the public pages and lists them in the sitemap', function () {
        config(['seo.index_environments' => ['testing']]);

        $this->get('/about')->assertSee('content="index, follow', false);
        $this->get(route('seo.sitemap'))->assertOk()
            ->assertSee('/about</loc>', false)
            ->assertSee('/blog</loc>', false)
            ->assertSee('/blog/preparing-for-a-dot-audit</loc>', false);
    });
});

describe('blog', function () {
    it('lists published posts and hides drafts and scheduled posts', function () {
        config(['seo.index_environments' => ['testing']]);
        Post::query()->create(['title' => 'Draft post', 'slug' => 'draft-post']);
        Post::query()->create(['title' => 'Future post', 'slug' => 'future-post', 'published_at' => now()->addDay()]);

        $this->get('/blog')->assertOk()
            ->assertSee('Five Steps to Prepare for a DOT Audit')
            ->assertDontSee('Draft post')
            ->assertDontSee('Future post');

        $this->get('/blog/draft-post')->assertNotFound();
        $this->get('/blog/future-post')->assertNotFound();
        $this->get('/blog/preparing-for-a-dot-audit')->assertOk()->assertSee('"@type":"BlogPosting"', false);
    });

    it('closes the blog when its page is hidden', function () {
        Page::query()->where('slug', 'blog')->sole()->update(['is_published' => false]);

        $this->get('/blog')->assertNotFound();
        $this->get('/blog/preparing-for-a-dot-audit')->assertNotFound();
    });

    it('creates a post with a cover, sanitizes the body and audits it', function () {
        Storage::fake('public');

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->post(route('platform.content.posts.store'), [
                'title' => 'Hours of Service Basics',
                'body' => '<p onclick="steal()">Hi <a href="javascript:alert(1)">x</a></p><script>alert(1)</script>',
                'published_at' => now()->subHour()->format('Y-m-d\TH:i'),
                'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 630),
            ])
            ->assertSessionHasNoErrors();

        $post = Post::query()->where('slug', 'hours-of-service-basics')->sole();
        expect($post->body)->not->toContain('script')->not->toContain('onclick')->not->toContain('javascript:')
            ->and($post->author_id)->not->toBeNull()
            ->and(AuditLog::where('action', 'post.created')->count())->toBe(1);
        Storage::disk('public')->assertExists($post->cover_image);
    });

    it('refuses a slug that collides with the company application route', function () {
        $this->actingAs(Actors::platformOwner(), 'platform')
            ->post(route('platform.content.posts.store'), ['title' => 'Apply'])
            ->assertSessionHasErrors('slug');
    });

    it('updates and deletes a post', function () {
        $post = Post::query()->where('slug', 'preparing-for-a-dot-audit')->sole();
        $owner = Actors::platformOwner();

        $this->actingAs($owner, 'platform')
            ->put(route('platform.content.posts.update', $post->id), ['title' => 'Audit prep', 'slug' => $post->slug, 'published_at' => ''])
            ->assertSessionHasNoErrors();
        expect($post->fresh()->published_at)->toBeNull();

        $this->actingAs($owner, 'platform')->delete(route('platform.content.posts.destroy', $post->id))->assertRedirect();
        expect(Post::query()->find($post->id))->toBeNull();
    });
});

describe('pages admin', function () {
    it('saves a page with sanitized HTML and audits it', function () {
        $page = Page::query()->where('slug', 'about')->sole();

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->put(route('platform.content.pages.update', $page), [
                'title' => 'About the team',
                'summary' => 'Who we are.',
                'body' => '<h2>Hello</h2><img src=x onerror=alert(1)><a href="https://example.com">site</a>',
                'is_published' => '1',
            ])
            ->assertRedirect(route('platform.content.pages.index'));

        $page->refresh();
        expect($page->title)->toBe('About the team')
            ->and($page->body)->toContain('<h2>Hello</h2>')->toContain('href="https://example.com"')->not->toContain('onerror')->not->toContain('<img')
            ->and(AuditLog::where('action', 'page.updated')->count())->toBe(1);

        $this->get('/about')->assertSee('About the team');
    });

    it('keeps the content screens from staff without platform.content.manage', function () {
        $staff = Actors::platformStaff('billing_manager');

        $this->actingAs($staff, 'platform')->get(route('platform.content.pages.index'))->assertForbidden();
        $this->actingAs($staff, 'platform')->get(route('platform.content.footer.edit'))->assertForbidden();
        $this->actingAs($staff, 'platform')->get(route('platform.content.posts.index'))->assertForbidden();
    });

    it('renders the admin screens', function () {
        $owner = Actors::platformOwner();

        $this->actingAs($owner, 'platform')->get(route('platform.content.pages.index'))->assertOk()->assertSee('Template, review before launch');
        $this->actingAs($owner, 'platform')->get(route('platform.content.pages.edit', 'about'))->assertOk()->assertSee('<trix-editor', false);
        $this->actingAs($owner, 'platform')->get(route('platform.content.posts.index'))->assertOk();
        $this->actingAs($owner, 'platform')->get(route('platform.content.posts.create'))->assertOk();
        $this->actingAs($owner, 'platform')->get(route('platform.content.footer.edit'))->assertOk()->assertSee('Link columns');
    });
});

describe('footer', function () {
    function footerInput(array $overrides = []): array
    {
        return array_replace_recursive(Footer::defaults(), ['cta' => ['enabled' => '1'], 'bottom' => ['back_to_top' => '1']], $overrides);
    }

    it('saves the footer, dropping blank rows, and shows it on public pages', function () {
        $input = footerInput(['brand' => ['tagline' => 'Trusted by fleets.'], 'contact' => ['email' => 'hello@example.com']]);
        $input['social'] = [['network' => 'linkedin', 'url' => 'https://linkedin.com/company/acme'], ['network' => 'x', 'url' => '']];
        $input['columns'] = [['heading' => 'Explore', 'links' => [
            ['label' => 'Our story', 'target' => 'page:about'],
            ['label' => '', 'target' => 'page:blog'],
        ]]];
        $input['bottom']['copyright'] = '© {year} Acme Freight';

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->put(route('platform.content.footer.update'), $input)
            ->assertSessionHasNoErrors();

        $saved = FooterSetting::query()->sole()->content;
        expect($saved['social'])->toEqual([['network' => 'linkedin', 'url' => 'https://linkedin.com/company/acme']])
            ->and($saved['columns'][0]['links'])->toEqual([['label' => 'Our story', 'target' => 'page:about']])
            ->and(AuditLog::where('action', 'footer.updated')->count())->toBe(1);

        $this->get('/security')->assertOk()
            ->assertSee('Trusted by fleets.')
            ->assertSee('mailto:hello@example.com', false)
            ->assertSee('href="https://linkedin.com/company/acme"', false)
            ->assertSee('Explore')
            ->assertSee('© '.date('Y').' Acme Freight');
    });

    it('rejects custom link URLs, unknown networks and plain-http profiles', function () {
        $input = footerInput();
        $input['columns'][0]['links'][0]['target'] = 'https://evil.example.com';
        $input['social'] = [['network' => 'myspace', 'url' => 'https://myspace.com/x'], ['network' => 'x', 'url' => 'http://x.com/acme']];

        $this->actingAs(Actors::platformOwner(), 'platform')
            ->put(route('platform.content.footer.update'), $input)
            ->assertSessionHasErrors(['columns.0.links.0.target', 'social.0.network', 'social.1.url']);
    });

    it('shows the sign-up band on inner pages but not on the homepage', function () {
        $this->get('/about')->assertSee(Footer::defaults()['cta']['title']);
        $this->get('/')->assertDontSee(Footer::defaults()['cta']['title']);
    });

    it('puts the social profiles in the homepage schema', function () {
        config(['seo.index_environments' => ['testing']]);
        $footer = FooterSetting::query()->sole();
        $footer->update(['content' => ['social' => [['network' => 'facebook', 'url' => 'https://facebook.com/acme']]] + $footer->content]);

        $this->get('/')->assertSee('"sameAs":["https://facebook.com/acme"]', false);
    });
});

describe('contact form', function () {
    beforeEach(function () {
        RateLimiter::clear('contact');
        SiteSetting::getSettings()->update(['support_email' => 'support@example.com']);
    });

    it('stores the message in the inbox as a contact message and mails support', function () {
        Mail::fake();

        $this->post(route('contact.store'), ['name' => 'Dana', 'email' => 'dana@example.com', 'message' => 'Do you support Canada?'])
            ->assertRedirect(route('pages.show', 'contact').'#contact-form');

        expect(DemoRequest::query()->sole()->source)->toBe(DemoRequest::SOURCE_CONTACT);
        Mail::assertQueued(DemoRequestReceivedMail::class, fn ($mail) => $mail->envelope()->subject === 'New contact message from Dana');

        $this->get('/contact')->assertSee('Thank you!');
        $this->actingAs(Actors::platformOwner(), 'platform')
            ->get(route('platform.content.demo-requests.index', ['source' => 'contact']))
            ->assertOk()->assertSee('Contact message')->assertSee('Do you support Canada?');
    });

    it('rejects the honeypot and a missing message', function () {
        $this->post(route('contact.store'), ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Hi', 'website' => 'spam'])
            ->assertSessionHasErrors('website');
        $this->post(route('contact.store'), ['name' => 'Dana', 'email' => 'dana@example.com'])
            ->assertSessionHasErrors('message');

        expect(DemoRequest::query()->count())->toBe(0);
    });

    it('is throttled', function () {
        foreach (range(1, 5) as $i) {
            $this->post(route('contact.store'), ['name' => 'Dana', 'email' => 'dana@example.com', 'message' => "Try {$i}"]);
        }

        $this->post(route('contact.store'), ['name' => 'Dana', 'email' => 'dana@example.com', 'message' => 'Again'])->assertStatus(429);
    });
});

it('links the legal pages from the register page', function () {
    $this->get(route('register'))->assertSee('By creating an account you agree to our', false)
        ->assertSee('href="'.route('pages.show', 'terms-of-service').'"', false);
});

it('strips unsafe markup but keeps formatting', function () {
    $html = RichText::clean('<div><strong>Bold</strong> <em>it</em><ul><li>One</li></ul><a href="mailto:a@b.co">mail</a><iframe src="https://x"></iframe><a href="/contact" style="color:red">c</a></div>');

    expect($html)->toContain('<strong>Bold</strong>')->toContain('<li>One</li>')->toContain('href="mailto:a')->toContain('href="/contact"')
        ->not->toContain('iframe')->not->toContain('style=');
});

<?php

use App\Models\SiteSetting;
use App\Services\Auth\SocialLoginService;
use Database\Factories\PlanFactory;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

beforeEach(function () {
    config(['seo.index_environments' => ['testing'], 'app.url' => 'https://example.com']);
});

it('serves the home page as indexable with canonical, og and json-ld', function () {
    PlanFactory::new()->withPrices(49.00)->create(['name' => 'Starter', 'slug' => 'starter']);

    $response = $this->get('/?utm_source=x')->assertOk();

    $response->assertHeaderMissing('X-Robots-Tag');
    $response->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false);
    $response->assertSee('<link rel="canonical" href="https://example.com/">', false);
    $response->assertSee('property="og:title"', false);
    $response->assertSee('application/ld+json', false);
    $response->assertSee('"price":"49.00"', false);
    $response->assertSee('"priceCurrency":"USD"', false);
    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
});

it('makes login and register indexable', function () {
    foreach (['/login', '/register'] as $url) {
        $this->get($url)->assertOk()->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('rel="canonical"', false)
            ->assertSee('<meta name="description"', false);
    }
});

it('keeps the platform and password auth pages noindex', function () {
    foreach (['/platform/login', '/platform/forgot-password', '/forgot-password'] as $url) {
        $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('content="noindex, nofollow"', false)
            ->assertDontSee('rel="canonical"', false);
    }
});

it('keeps the social sign-in pages noindex (P1-3)', function () {
    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
    SiteSetting::getSettings()->update(['google_login_enabled' => true]);
    Socialite::fake('google', fn () => throw new InvalidStateException);

    $this->get('/auth/google/callback')->assertRedirect()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->withSession([SocialLoginService::PENDING => [
        'provider' => 'google', 'provider_user_id' => '1', 'email' => 'new@example.com', 'name' => 'New',
        'email_verified' => true, 'expires_at' => now()->addMinutes(5)->getTimestamp(),
    ]])->get('/auth/google/finish')->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('content="noindex, nofollow"', false);
    // The buttons don't change the public status of login and register
    $this->get('/login')->assertOk()->assertSee('Continue with Google')->assertHeaderMissing('X-Robots-Tag');
    $this->get('/register')->assertOk()->assertHeaderMissing('X-Robots-Tag');
    expect($this->get('/robots.txt')->getContent())->toContain('Disallow: /auth/');
});

it('marks every non-whitelisted page noindex by header and meta', function () {
    $this->get('/dashboard')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/some-company/apply')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('never indexes outside the configured environments', function () {
    config(['seo.index_environments' => ['production']]);

    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('content="noindex, nofollow"', false)
        ->assertDontSee('rel="canonical"', false);
    $this->get('/robots.txt')->assertSee('Disallow: /', false)->assertDontSee('Sitemap:');
});

it('serves robots.txt with private paths and the sitemap in production', function () {
    $this->get('/robots.txt')->assertOk()
        ->assertSee('Disallow: /admin', false)
        ->assertSee('Disallow: /platform', false)
        ->assertSee('Sitemap: ', false)
        ->assertHeaderMissing('X-Robots-Tag');
});

it('lists only public urls in the sitemap', function () {
    $response = $this->get('/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/xml');
    $response->assertSee('<loc>https://example.com/</loc>', false)->assertDontSee('/admin')->assertDontSee('/dashboard');
});

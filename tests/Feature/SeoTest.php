<?php

use App\Models\Plan;

beforeEach(function () {
    config(['seo.index_environments' => ['testing'], 'app.url' => 'https://example.com']);
});

it('serves the home page as indexable with canonical, og and json-ld', function () {
    Plan::create(['name' => 'Starter', 'slug' => 'starter', 'is_active' => true, 'price' => 49, 'currency' => 'usd', 'billing_cycle' => 'monthly']);

    $response = $this->get('/?utm_source=x')->assertOk();

    $response->assertHeaderMissing('X-Robots-Tag');
    $response->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false);
    $response->assertSee('<link rel="canonical" href="https://example.com/">', false);
    $response->assertSee('property="og:title"', false);
    $response->assertSee('application/ld+json', false);
    $response->assertSee('"priceCurrency":"USD"', false);
    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
});

it('makes login and register indexable', function () {
    foreach (['/login', '/register'] as $url) {
        $this->get($url)->assertOk()->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('rel="canonical"', false);
    }
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
        ->assertSee('Sitemap: ', false)
        ->assertHeaderMissing('X-Robots-Tag');
});

it('lists only public urls in the sitemap', function () {
    $response = $this->get('/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/xml');
    $response->assertSee('<loc>https://example.com/</loc>', false)->assertDontSee('/admin')->assertDontSee('/dashboard');
});

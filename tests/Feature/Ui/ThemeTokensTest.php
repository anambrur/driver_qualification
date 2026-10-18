<?php

use Illuminate\Support\Facades\File;
use Tests\Support\Actors;

/*
| UI-14 (phase 2, batch 5c): pages use theme tokens (foreground, muted, border, primary…), not the
| legacy gray-* / brand-* scales, and app.css no longer defines those scales. The 404 page is on x-ui.
*/

it('has no gray-* or brand-* utility left in views, scripts or models', function () {
    $files = collect([resource_path('views'), resource_path('js'), app_path()])
        ->flatMap(fn ($dir) => File::allFiles($dir))
        ->filter(fn ($file) => preg_match('/(?<![\w-])(?:[a-z-]+:)*(?:text|bg|border|ring|from|via|to|divide|placeholder|shadow)-(?:gray|brand)-\d+/', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values();

    expect($files->all())->toBe([]);
});

it('no longer defines the gray and brand scales in app.css', function () {
    expect(File::get(resource_path('css/app.css')))
        ->not->toContain('--color-gray-')
        ->not->toContain('--color-brand-');
});

it('renders the token-based pages', function (string $route) {
    $owner = Actors::companyOwner();
    $url = $route === 'admin.settings.company.edit' ? route($route, $owner->company_id) : route($route);

    expect($this->actingAs($owner)->get($url)->assertOk()->getContent())->not->toMatch('/-(gray|brand)-\d/');
})->with(['admin.roles.index', 'admin.roles.create', 'admin.settings.company.edit', 'admin.settings.policy.pdf', 'users.index', 'users.invite']);

it('keeps the welcome footer dark in both themes', function () {
    $this->get('/')->assertOk()
        ->assertSee('<footer class="dark bg-background', false)
        ->assertDontSee('-gray-', false)
        ->assertDontSee('-brand-5', false);
});

it('keeps the auth panel gradient as a mix of the brand colour', function () {
    $this->get(route('login'))->assertOk()
        ->assertSee('from-[color-mix(in_oklch,var(--primary)_74%,black)]', false);
});

it('serves missing pages from the x-ui 404 page, noindex and with a 404 status', function () {
    $this->get('/this-page-does-not-exist-'.uniqid())
        ->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee(['Page not found', 'Back to home'])
        ->assertSee('class="empty border', false)
        ->assertDontSee('TailAdmin');

    expect(view()->exists('404'))->toBeFalse()
        ->and(view()->exists('partials.preloader'))->toBeFalse();
});

it('still forbids the users list without users.view', function () {
    $this->actingAs(Actors::memberWithPermissions(Actors::company(), ['drivers.view']))
        ->get(route('users.index'))
        ->assertForbidden();
});

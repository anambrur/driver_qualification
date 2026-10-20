<?php

use Illuminate\Support\Facades\File;

/*
| UI-32 (phase 2, batch 9c): the TailAdmin demo assets are gone. Nothing referenced the images
| outside public/images/logo, the TailAdmin favicon in public/images, or public/js/index.js and
| its chart/map/calendar components. The TailAdmin wordmarks (logo.svg, logo-dark.svg) are gone
| too (UX follow-ups #12): without a settings logo, pages show logo-icon.svg and the site name.
*/

it('no longer ships the TailAdmin demo images and scripts', function (string $path) {
    expect(file_exists(public_path($path)))->toBeFalse();
})->with([
    'images/brand', 'images/country', 'images/error', 'images/grid-image', 'images/icons',
    'images/product', 'images/shape', 'images/user', 'images/video-thumb', 'images/favicon.ico',
    'js/index.js', 'js/components', 'images/logo/logo.svg', 'images/logo/logo-dark.svg',
]);

it('keeps only the logo icon and the driver form script', function () {
    $files = collect([public_path('images'), public_path('js')])
        ->flatMap(fn ($dir) => File::allFiles($dir))
        ->map(fn ($file) => str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()))
        ->sort()
        ->values()
        ->all();

    expect($files)->toBe(['driver-form-validation.js', 'logo/logo-icon.svg']);
});

it('shows the logo icon and the site name without a settings logo', function (string $page) {
    $this->get($page === 'welcome' ? '/' : route('login'))
        ->assertOk()
        ->assertSee(asset('images/logo/logo-icon.svg'), false)
        ->assertSee(settings('site_name', config('app.name')))
        ->assertDontSee('images/logo/logo.svg', false)
        ->assertDontSee('images/logo/logo-dark.svg', false);
})->with(['welcome', 'login']);

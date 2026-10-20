<?php

use Illuminate\Support\Facades\File;

/*
| UI-32 (phase 2, batch 9c): the TailAdmin demo assets are gone. Nothing referenced the images
| outside public/images/logo, the TailAdmin favicon in public/images, or public/js/index.js and
| its chart/map/calendar components. The logos the welcome page, auth panel and sidebar use stay.
*/

it('no longer ships the TailAdmin demo images and scripts', function (string $path) {
    expect(file_exists(public_path($path)))->toBeFalse();
})->with([
    'images/brand', 'images/country', 'images/error', 'images/grid-image', 'images/icons',
    'images/product', 'images/shape', 'images/user', 'images/video-thumb', 'images/favicon.ico',
    'js/index.js', 'js/components',
]);

it('keeps only the logos and the driver form script', function () {
    $files = collect([public_path('images'), public_path('js')])
        ->flatMap(fn ($dir) => File::allFiles($dir))
        ->map(fn ($file) => str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()))
        ->sort()
        ->values()
        ->all();

    expect($files)->toBe(['driver-form-validation.js', 'logo/logo-dark.svg', 'logo/logo-icon.svg', 'logo/logo.svg']);
});

it('still serves the logos the pages use', function () {
    $html = $this->get('/')->assertOk()->getContent().$this->get(route('login'))->assertOk()->getContent();

    foreach (['images/logo/logo.svg', 'images/logo/logo-dark.svg'] as $logo) {
        expect($html)->toContain(asset($logo))
            ->and(file_exists(public_path($logo)))->toBeTrue();
    }
});

<?php

use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;

/*
| SET-01: the logo had no validation at all, so a PHP file was stored on the public disk as
| settings/<hash>.php. SET-03: favicon must not accept SVG (stored XSS when opened from /storage).
| Both uploads are restricted to raster images and stored under a content-derived extension.
*/

function updateSiteSettings($test, array $files)
{
    return $test->actingAs(Actors::superAdmin())
        ->from(route('admin.settings.site.index'))
        ->put(route('admin.settings.site.update'), array_merge(['site_name' => 'DriverFilesHub'], $files));
}

/**
 * A real upload whose MIME type is sniffed from its content, as in production.
 * (UploadedFile::fake() derives the MIME type from the file *name*, so it can't test spoofing.)
 */
function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upl');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

function icoFile(string $name = 'favicon.ico'): UploadedFile
{
    return realUpload($name, "\x00\x00\x01\x00\x01\x00\x10\x10\x00\x00\x01\x00\x20\x00\x68\x04\x00\x00\x16\x00\x00\x00".str_repeat("\x00", 1128));
}

beforeEach(function () {
    Storage::fake('public');
});

describe('logo', function () {
    it('rejects a PHP file', function () {
        updateSiteSettings($this, ['logo' => UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]); ?>')])
            ->assertSessionHasErrors('logo');

        expect(Storage::disk('public')->allFiles())->toBe([])
            ->and(SiteSetting::first()->logo)->toBeNull();
    });

    it('rejects an HTML file renamed to .png', function () {
        updateSiteSettings($this, ['logo' => realUpload('logo.png', '<html><script>alert(1)</script></html>')])
            ->assertSessionHasErrors('logo');

        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('rejects an SVG logo', function () {
        updateSiteSettings($this, ['logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
            ->assertSessionHasErrors('logo');

        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('rejects a plain string so the stored path cannot be pointed at another file', function () {
        updateSiteSettings($this, ['logo' => 'companies/other-tenant-document.pdf'])
            ->assertSessionHasErrors('logo');

        expect(SiteSetting::first()->logo)->toBeNull();
    });

    it('stores a real image under a hashed name with an extension derived from its content', function () {
        updateSiteSettings($this, ['logo' => UploadedFile::fake()->image('logo.php.png', 120, 40)])
            ->assertSessionHasNoErrors();

        $logo = SiteSetting::first()->logo;
        expect($logo)->toMatch('#^settings/[A-Za-z0-9]{40}\.png$#');
        Storage::disk('public')->assertExists($logo);
    });
});

describe('favicon', function () {
    it('rejects an SVG favicon', function () {
        updateSiteSettings($this, ['favicon' => UploadedFile::fake()->createWithContent('favicon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
            ->assertSessionHasErrors('favicon');

        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('rejects a PHP file named .ico', function () {
        updateSiteSettings($this, ['favicon' => realUpload('favicon.ico', '<?php system($_GET["c"]); ?>')])
            ->assertSessionHasErrors('favicon');

        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('accepts a real .ico favicon (the form offers image/x-icon)', function () {
        updateSiteSettings($this, ['favicon' => icoFile()])
            ->assertSessionHasNoErrors();

        $favicon = SiteSetting::first()->favicon;
        expect($favicon)->toMatch('#^settings/[A-Za-z0-9]{40}\.ico$#');
        Storage::disk('public')->assertExists($favicon);
    });

    it('accepts a png favicon', function () {
        updateSiteSettings($this, ['favicon' => UploadedFile::fake()->image('favicon.png', 32, 32)])
            ->assertSessionHasNoErrors();

        expect(SiteSetting::first()->favicon)->toMatch('#^settings/[A-Za-z0-9]{40}\.png$#');
    });
});

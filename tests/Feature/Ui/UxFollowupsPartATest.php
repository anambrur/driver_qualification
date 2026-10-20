<?php

use App\Models\SiteSetting;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\DriverCompliance\DriverCompliance;
use Tests\Support\Actors;

/*
| UX follow-ups part A: the compliance dashboard's CDL row (no document type) links to the license
| step like driver/show, every layout shares partials/favicon with a logo-icon fallback and a real
| favicon.ico, and tests may not make real HTTP requests.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

describe('compliance dashboard CDL row', function () {
    it('links a missing CDL to the license step without upload or reminder buttons', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $driver->licenses()->delete();

        $html = $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk()
            ->assertSee("Commercial Driver's License")
            ->getContent();

        expect($html)->toContain('href="'.route('admin.driver.license', ['driver_id' => $driver->id, 'edit' => '1']).'"')
            ->not->toContain('data-type-id=""')
            ->not->toContain('data-type-id ');
    });

    it('keeps the upload and reminder buttons on rows with a document type', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType(['name' => 'Road Test']);

        $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk()
            ->assertSee('data-action="upload-document" data-id="'.$driver->id.'" data-type-id="'.$type->id.'"', false)
            ->assertSee('data-action="send-reminder" data-id="'.$driver->id.'" data-type-id="'.$type->id.'"', false)
            ->assertDontSee(route('admin.driver.license', ['driver_id' => $driver->id, 'edit' => '1']), false);
    });

    it('hides the CDL link without drivers.create or drivers.edit', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $driver->licenses()->delete();
        $viewer = Actors::memberWithPermissions(Actors::companyOf($owner), ['drivers.dashboard']);

        $this->actingAs($viewer)->get(route('admin.compliance.drivers'))->assertOk()
            ->assertSee("Commercial Driver's License")
            ->assertDontSee(route('admin.driver.license', ['driver_id' => $driver->id, 'edit' => '1']), false);
    });
});

describe('favicon', function () {
    it('uses the shared partial in every layout', function (string $view) {
        expect(File::get(resource_path("views/{$view}.blade.php")))
            ->toContain("@include('partials.favicon')")
            ->not->toContain("settings('favicon')");
    })->with([
        'layouts/main-layout',
        'layouts/platform',
        'layouts/application-form-layout',
        'components/layout/auth',
        'errors/404',
        'welcome',
    ]);

    it('falls back to the logo icon and favicon.ico', function () {
        $this->get('/')->assertOk()
            ->assertSee('<link rel="icon" href="'.asset('favicon.ico').'" sizes="any">', false)
            ->assertSee('<link rel="icon" type="image/svg+xml" href="'.asset('images/logo/logo-icon.svg').'">', false);

        $this->get('/no-such-page-'.uniqid())->assertNotFound()
            ->assertSee(asset('images/logo/logo-icon.svg'), false);
    });

    it('uses the uploaded favicon when one is set', function () {
        SiteSetting::getSettings()->update(['favicon' => 'settings/icon.png']);

        $this->get('/')->assertOk()
            ->assertSee('<link rel="icon" type="image/png" href="'.asset('storage/settings/icon.png').'">', false)
            ->assertSee('<link rel="apple-touch-icon" href="'.asset('storage/settings/icon.png').'">', false)
            ->assertDontSee('<link rel="icon" type="image/svg+xml" href="'.asset('images/logo/logo-icon.svg').'">', false);
    });

    it('ships a real favicon.ico', function () {
        $bytes = File::get(public_path('favicon.ico'));

        expect(strlen($bytes))->toBeGreaterThan(0)
            ->and(substr($bytes, 0, 4))->toBe("\x00\x00\x01\x00");
    });
});

it('blocks stray HTTP requests in every test', function () {
    expect(fn () => Http::get('https://example.com'))->toThrow(StrayRequestException::class);
});

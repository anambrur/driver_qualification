<?php

use App\Models\SiteSetting;
use App\Support\Ui\Color;
use App\Support\Ui\Theme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;

/*
| UI-01: Platform → Settings → Site → Appearance. One brand colour (plus an accent and the
| default colour mode) themes every shell through partials/theme-head.
*/

function appearancePayload(array $overrides = []): array
{
    return array_merge([
        'site_name' => 'DriverFilesHub',
        'trial_days' => 14,
        'grace_days' => 7,
        'support_session_max_minutes' => 240,
    ], $overrides);
}

beforeEach(function () {
    Storage::fake('public');
});

it('saves the brand colour, accent and colour mode', function () {
    $this->actingAs(Actors::platformOwner(), 'platform')
        ->put(route('platform.settings.site.update'), appearancePayload([
            'primary_color' => '#0EA5E9',
            'accent_color' => '#f97316',
            'default_color_mode' => 'dark',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $setting = SiteSetting::first();
    expect($setting->primary_color)->toBe('#0ea5e9')
        ->and($setting->accent_color)->toBe('#f97316')
        ->and($setting->default_color_mode)->toBe('dark');
});

it('rejects colours that are not six-digit hex', function (string $value) {
    $this->actingAs(Actors::platformOwner(), 'platform')
        ->put(route('platform.settings.site.update'), appearancePayload(['primary_color' => $value]))
        ->assertSessionHasErrors('primary_color');
})->with(['red', '#fff', '#12345g', '#123456;}', 'url(x)']);

it('rejects an unknown colour mode', function () {
    $this->actingAs(Actors::platformOwner(), 'platform')
        ->put(route('platform.settings.site.update'), appearancePayload(['default_color_mode' => 'sepia']))
        ->assertSessionHasErrors('default_color_mode');
});

it('stores a dark-mode logo on the public disk', function () {
    $this->actingAs(Actors::platformOwner(), 'platform')
        ->put(route('platform.settings.site.update'), appearancePayload([
            'logo_dark' => UploadedFile::fake()->image('logo-dark.png', 120, 40),
        ]))
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertExists(SiteSetting::first()->logo_dark);
});

it('does not let platform support change the appearance', function () {
    $this->actingAs(Actors::platformSupport(), 'platform')
        ->put(route('platform.settings.site.update'), appearancePayload(['primary_color' => '#000000']))
        ->assertForbidden();

    expect(SiteSetting::first()?->primary_color)->toBeNull();
});

it('renders the brand colour and a readable foreground into every shell', function () {
    SiteSetting::getSettings()->update(['primary_color' => '#0ea5e9', 'default_color_mode' => 'dark']);

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('--brand:#0ea5e9;--brand-foreground:'.Color::foreground('#0ea5e9'), false)
        ->assertSee('document.documentElement, "dark"', false);
});

it('themes the company shell too', function () {
    SiteSetting::getSettings()->update(['primary_color' => '#0ea5e9']);

    $this->actingAs(Actors::companyOwner())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('--brand:#0ea5e9', false);
});

it('falls back to the default colour when the stored value is not a hex colour', function () {
    // The column is varchar(7); 7 characters is enough to break out of the CSS declaration.
    SiteSetting::getSettings()->update(['primary_color' => 'red}</s']);

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('--brand:'.Theme::DEFAULT_BRAND, false)
        ->assertDontSee('--brand:red}', false);
});

it('picks white or near-black text for contrast', function (string $brand, string $foreground) {
    expect(Color::foreground($brand))->toBe($foreground);
})->with([
    'dark blue' => ['#1d4ed8', '#ffffff'],
    'default brand' => [Theme::DEFAULT_BRAND, '#ffffff'],
    'yellow' => ['#facc15', '#101828'],
    'white' => ['#ffffff', '#101828'],
]);

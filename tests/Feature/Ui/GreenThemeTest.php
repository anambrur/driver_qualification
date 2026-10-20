<?php

use App\Models\SiteSetting;
use App\Support\Ui\Color;
use App\Support\Ui\Theme;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Actors;

/*
| Green theme by default: deep green brand, gold accent, and a sidebar that is dark in both modes
| (--sidebar-base, a shade of the brand). Old stored defaults are reset so the new ones apply.
*/

function whiteContrast(string $hex): float
{
    $channels = array_map(function (string $pair): float {
        $c = hexdec($pair) / 255;

        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }, str_split(substr($hex, 1), 2));

    return 1.05 / (0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2] + 0.05);
}

it('defaults to the green brand and gold accent', function () {
    expect(Theme::current())
        ->brand->toBe('#1f5c4f')
        ->highlight->toBe('#d4a24c')
        ->sidebar->toBe('#0b3a38');
});

it('prints the sidebar shade of the brand in every shell', function () {
    SiteSetting::getSettings()->update(['primary_color' => '#0ea5e9']);

    $this->actingAs(Actors::companyOwner())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('--sidebar-base:'.Color::shade('#0ea5e9'), false);

    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('--sidebar-base:'.Color::shade('#0ea5e9'), false);
});

it('shades any brand dark enough for white sidebar text', function (string $brand) {
    $shade = Color::shade($brand);

    expect($shade)->toMatch(Color::HEX)
        ->and(whiteContrast($shade))->toBeGreaterThanOrEqual(7.0);
})->with(['#1f5c4f', '#465fff', '#facc15', '#ffffff', '#000000', '#ff0000', '#0ea5e9']);

it('greets the user on the dashboard', function () {
    $owner = Actors::companyOwner();
    $owner->forceFill(['name' => 'Maya Bennett'])->save();

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Welcome back, Maya');
});

it('resets only the old default colours', function (?string $primary, ?string $accent, ?string $primaryAfter, ?string $accentAfter) {
    SiteSetting::getSettings()->update(['primary_color' => $primary, 'accent_color' => $accent]);

    (require database_path('migrations/2026_10_20_000200_reset_old_default_theme_colors.php'))->up();

    expect(Cache::has('site_settings'))->toBeFalse()
        ->and(SiteSetting::first())
        ->primary_color->toBe($primaryAfter)
        ->accent_color->toBe($accentAfter);
})->with([
    'old defaults' => ['#465fff', '#0ba5ec', null, null],
    'old defaults, upper case' => ['#465FFF', '#0BA5EC', null, null],
    'custom colours' => ['#0ea5e9', '#f97316', '#0ea5e9', '#f97316'],
]);

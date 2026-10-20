<?php

use App\Models\SiteSetting;
use Tests\Support\Actors;

/*
| UI-11 (phase 2, batch 4a-4): site settings and live chat on x-ui fields, and the platform layout
| without the Font Awesome kit (no platform page uses it any more).
*/

it('renders both settings pages on x-ui fields without Font Awesome', function (string $route) {
    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route($route))
        ->assertOk()
        ->assertSee('class="field', false)
        ->assertDontSee('kit.fontawesome.com', false)
        ->assertDontSee('class="fas ', false)
        ->assertDontSee('dark:bg-dark-900', false);
})->with(['platform.settings.site.index', 'platform.settings.tawk.index']);

it('keeps the appearance section and every site setting field', function () {
    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.settings.site.index'))
        ->assertOk()
        ->assertSee('id="appearance"', false)
        ->assertSee(['name="site_name"', 'name="logo"', 'name="favicon"', 'name="meta_description"', 'name="google_analytics_id"',
            'name="legal_name"', 'name="trial_plan_id"', 'name="trial_days"', 'name="grace_days"', 'name="support_session_max_minutes"'], false);
});

it('shows the live chat switch in its saved state', function () {
    SiteSetting::getSettings()->forceFill(['tawk_enabled' => true])->save();

    $html = $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.settings.tawk.index'))
        ->assertOk()->getContent();

    expect($html)->toMatch('~<input type="hidden" name="tawk_enabled" value="0">~')
        ->toMatch('~name="tawk_enabled"\s+id="f-tawk_enabled"\s+value="1"\s+role="switch"\s+checked~');
});

it('loads no Font Awesome on any platform page', function () {
    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertDontSee('kit.fontawesome.com', false);
});

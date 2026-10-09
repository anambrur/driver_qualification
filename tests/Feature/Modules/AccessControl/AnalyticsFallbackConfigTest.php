<?php

use Tests\Support\Actors;

/*
| ACL-08: the welcome page and main layout fell back to env('GA_MEASUREMENT_ID') when no GA id
| is saved in site settings. Once `php artisan config:cache` has run, env() returns null outside
| config files, so the fallback silently disappeared in production. It is now read from config.
*/

it('falls back to the configured GA id when site settings have none', function (string $page) {
    config(['app.ga_measurement_id' => 'G-FROMCONFIG1']);

    $request = $page === 'welcome' ? $this : $this->actingAs(Actors::companyOwner());

    $request->get($page === 'welcome' ? '/' : route('admin.dashboard'))
        ->assertOk()
        ->assertSee('gtag/js?id=G-FROMCONFIG1', false);
})->with(['welcome', 'main layout']);

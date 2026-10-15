<?php

use App\Models\AuditLog;
use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;

/*
| SET-01 / SET-02: site settings (name, logo, meta tags, contact info, GA id) are global and
| rendered on every page, so only platform staff holding platform.settings.manage may read or
| change them, in /platform (WP8). Tenants can't reach those routes at all.
*/

function siteSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'site_name' => 'Hijacked Site',
        'meta_title' => 'Hijacked title',
        'meta_description' => 'Hijacked description',
        'email' => 'attacker@example.com',
        'google_analytics_id' => 'G-ATTACKER1',
        'trial_days' => 14,
        'grace_days' => 7,
        'support_session_max_minutes' => 240,
    ], $overrides);
}

beforeEach(function () {
    Storage::fake('public');
    SiteSetting::getSettings()->update(['site_name' => 'DriverFilesHub', 'google_analytics_id' => 'G-ORIGINAL1']);
});

describe('tenants', function () {
    it('cannot update global site settings', function () {
        $this->actingAs(Actors::companyOwner())
            ->put(route('platform.settings.site.update'), siteSettingsPayload())
            ->assertRedirect(route('platform.login'));

        $setting = SiteSetting::first();
        expect($setting->site_name)->toBe('DriverFilesHub')
            ->and($setting->google_analytics_id)->toBe('G-ORIGINAL1')
            ->and($setting->meta_title)->toBeNull();
    });

    it('cannot open the site settings page', function () {
        $this->actingAs(Actors::companyOwner())
            ->get(route('platform.settings.site.index'))
            ->assertRedirect(route('platform.login'));
    });

    it('cannot upload a logo', function () {
        $this->actingAs(Actors::companyOwner())
            ->put(route('platform.settings.site.update'), siteSettingsPayload([
                'logo' => UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]); ?>'),
            ]))
            ->assertRedirect(route('platform.login'));

        expect(Storage::disk('public')->allFiles('settings'))->toBe([]);
    });

    it('cannot update tawk.to settings either', function () {
        $this->actingAs(Actors::companyOwner())
            ->put(route('platform.settings.tawk.update'), ['tawk_enabled' => '0'])
            ->assertRedirect(route('platform.login'));
    });
});

it('forbids platform staff without platform.settings.manage', function () {
    $support = Actors::platformSupport();

    $this->actingAs($support, 'platform')->get(route('platform.settings.site.index'))->assertForbidden();
    $this->actingAs($support, 'platform')->put(route('platform.settings.site.update'), siteSettingsPayload())->assertForbidden();

    expect(SiteSetting::first()->site_name)->toBe('DriverFilesHub');
});

it('lets a Platform Owner view and update site settings, and audits it', function () {
    $admin = Actors::platformOwner();

    $this->actingAs($admin, 'platform')->get(route('platform.settings.site.index'))->assertOk();

    $this->actingAs($admin, 'platform')
        ->put(route('platform.settings.site.update'), siteSettingsPayload(['site_name' => 'New Name', 'google_analytics_id' => 'G-NEW123', 'grace_days' => 10]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $setting = SiteSetting::first();
    expect($setting->site_name)->toBe('New Name')
        ->and($setting->google_analytics_id)->toBe('G-NEW123')
        ->and($setting->grace_days)->toBe(10)
        ->and(AuditLog::where('action', 'site_settings.updated')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

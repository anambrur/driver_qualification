<?php

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;

/*
| SET-01 / SET-02: site settings (name, logo, meta tags, contact info, GA id) are global and
| rendered on every page, so only users holding settings.* (super-admin) may read or change them.
*/

function siteSettingsPayload(array $overrides = []): array
{
    return array_merge([
        'site_name' => 'Hijacked Site',
        'meta_title' => 'Hijacked title',
        'meta_description' => 'Hijacked description',
        'email' => 'attacker@example.com',
        'google_analytics_id' => 'G-ATTACKER1',
    ], $overrides);
}

beforeEach(function () {
    Storage::fake('public');
    SiteSetting::getSettings()->update(['site_name' => 'DriverFilesHub', 'google_analytics_id' => 'G-ORIGINAL1']);
});

describe('tenants', function () {
    it('cannot update global site settings', function () {
        $this->actingAs(Actors::companyOwner())
            ->put(route('admin.settings.site.update'), siteSettingsPayload())
            ->assertForbidden();

        $setting = SiteSetting::first();
        expect($setting->site_name)->toBe('DriverFilesHub')
            ->and($setting->google_analytics_id)->toBe('G-ORIGINAL1')
            ->and($setting->meta_title)->toBeNull();
    });

    it('cannot open the site settings page', function () {
        $this->actingAs(Actors::companyOwner())
            ->get(route('admin.settings.site.index'))
            ->assertForbidden();
    });

    it('cannot upload a logo', function () {
        $this->actingAs(Actors::companyOwner())
            ->put(route('admin.settings.site.update'), siteSettingsPayload([
                'logo' => \Illuminate\Http\UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]); ?>'),
            ]))
            ->assertForbidden();

        expect(Storage::disk('public')->allFiles('settings'))->toBe([]);
    });

    it('cannot update tawk.to settings either', function () {
        $this->actingAs(Actors::companyOwner())
            ->put(route('admin.settings.tawk.update'), ['tawk_enabled' => '0'])
            ->assertForbidden();
    });
});

it('forbids a user without settings permissions', function () {
    $this->actingAs(Actors::plainUser())
        ->put(route('admin.settings.site.update'), siteSettingsPayload())
        ->assertForbidden();

    expect(SiteSetting::first()->site_name)->toBe('DriverFilesHub');
});

it('lets a user with only settings.view read but not write', function () {
    $user = Actors::plainUser(['settings.view']);

    $this->actingAs($user)->get(route('admin.settings.site.index'))->assertOk();
    $this->actingAs($user)->put(route('admin.settings.site.update'), siteSettingsPayload())->assertForbidden();
});

it('lets the super-admin view and update site settings', function () {
    $admin = Actors::superAdmin();

    $this->actingAs($admin)->get(route('admin.settings.site.index'))->assertOk();

    $this->actingAs($admin)
        ->put(route('admin.settings.site.update'), siteSettingsPayload(['site_name' => 'New Name', 'google_analytics_id' => 'G-NEW123']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $setting = SiteSetting::first();
    expect($setting->site_name)->toBe('New Name')
        ->and($setting->google_analytics_id)->toBe('G-NEW123');
});

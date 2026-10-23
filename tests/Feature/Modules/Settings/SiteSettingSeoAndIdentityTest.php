<?php

use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Support\TimeZones;
use App\Support\Ui\Theme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;

/*
| 04 §8 site settings that had columns but no screen (14 §3, 23 Oct 2026): SEO defaults, the
| default time zone, the mail sender name and the email logo. Each one is also read where it
| takes effect, so saving it changes the public pages, the sign-up forms or the emails.
*/

function seoSettingsUpdate($test, array $fields)
{
    return $test->actingAs(Actors::platformOwner(), 'platform')
        ->from(route('platform.settings.site.index'))
        ->put(route('platform.settings.site.update'), array_merge(['site_name' => 'DriverFilesHub', 'trial_days' => 14, 'grace_days' => 7, 'support_session_max_minutes' => 60], $fields));
}

beforeEach(function () {
    Storage::fake('public');
    config(['seo.index_environments' => ['testing'], 'app.url' => 'https://example.com']);
});

it('shows the new fields on the settings page', function () {
    $this->actingAs(Actors::platformOwner(), 'platform')
        ->get(route('platform.settings.site.index'))
        ->assertOk()
        ->assertSee(['name="og_image"', 'name="twitter_card"', 'name="canonical_base_url"', 'name="robots_noindex_default"',
            'name="search_console_verification"', 'name="timezone"', 'name="mail_from_name"', 'name="email_logo"'], false);
});

it('saves the SEO, time zone and mail fields and audits them', function () {
    seoSettingsUpdate($this, [
        'og_image' => UploadedFile::fake()->image('share.png', 1200, 630),
        'email_logo' => UploadedFile::fake()->image('mail.png', 160, 32),
        'twitter_card' => 'summary',
        'canonical_base_url' => 'https://www.driverfileshub.test/',
        'robots_noindex_default' => '1',
        'search_console_verification' => 'abc-123_XYZ',
        'timezone' => 'America/Denver',
        'mail_from_name' => 'DFH Team',
    ])->assertSessionHasNoErrors();

    $setting = SiteSetting::query()->firstOrFail();
    expect($setting->only(['twitter_card', 'canonical_base_url', 'robots_noindex_default', 'search_console_verification', 'timezone', 'mail_from_name']))->toBe([
        'twitter_card' => 'summary',
        'canonical_base_url' => 'https://www.driverfileshub.test',
        'robots_noindex_default' => true,
        'search_console_verification' => 'abc-123_XYZ',
        'timezone' => 'America/Denver',
        'mail_from_name' => 'DFH Team',
    ]);
    Storage::disk('public')->assertExists([$setting->og_image, $setting->email_logo]);
    expect(AuditLog::query()->where('action', 'site_settings.updated')->latest('id')->value('changes'))
        ->toHaveKeys(['og_image', 'timezone', 'mail_from_name']);
});

it('rejects invalid values', function () {
    seoSettingsUpdate($this, [
        'twitter_card' => 'player',
        'canonical_base_url' => 'http://insecure.test',
        'search_console_verification' => '"><script>',
        'timezone' => 'Europe/London',
        'og_image' => UploadedFile::fake()->create('share.svg', 10, 'image/svg+xml'),
        'email_logo' => UploadedFile::fake()->image('mail.webp'),
    ])->assertSessionHasErrors(['twitter_card', 'canonical_base_url', 'search_console_verification', 'timezone', 'og_image', 'email_logo']);
});

it('keeps the time zone and card when the fields are not sent', function () {
    SiteSetting::getSettings()->update(['timezone' => 'America/Denver', 'twitter_card' => 'summary']);

    seoSettingsUpdate($this, [])->assertSessionHasNoErrors();

    expect(SiteSetting::query()->firstOrFail()->only(['timezone', 'twitter_card']))->toBe(['timezone' => 'America/Denver', 'twitter_card' => 'summary']);
});

it('hides the whole site when the noindex switch is on', function () {
    SiteSetting::getSettings()->update(['robots_noindex_default' => true]);

    $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('content="noindex, nofollow"', false)
        ->assertDontSee('rel="canonical"', false);
    $this->get('/robots.txt')->assertSee('Disallow: /', false)->assertDontSee('Sitemap:');
});

it('uses the canonical base URL, the share image, the card type and the verification tag on public pages', function () {
    SiteSetting::getSettings()->update([
        'canonical_base_url' => 'https://www.driverfileshub.test',
        'og_image' => 'settings/share.png',
        'twitter_card' => 'summary',
        'search_console_verification' => 'abc-123',
    ]);

    $this->get('/')->assertOk()
        ->assertSee('<link rel="canonical" href="https://www.driverfileshub.test/">', false)
        ->assertSee('<meta property="og:image" content="'.asset('storage/settings/share.png').'">', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<meta name="google-site-verification" content="abc-123">', false);
    $this->get('/sitemap.xml')->assertSee('<loc>https://www.driverfileshub.test/</loc>', false);
    $this->get('/robots.txt')->assertSee('Sitemap: https://www.driverfileshub.test/sitemap.xml', false);
});

it('leaves the verification tag off private pages', function () {
    SiteSetting::getSettings()->update(['search_console_verification' => 'abc-123']);

    $this->get(route('password.request'))->assertDontSee('google-site-verification', false);
});

it('prefers the email logo in emails', function () {
    SiteSetting::getSettings()->update(['logo' => 'settings/logo.png', 'email_logo' => 'settings/mail.png']);

    expect(Theme::emailLogo())->toBe(asset('storage/settings/mail.png'));
});

it('preselects the platform time zone on the sign-up form', function () {
    SiteSetting::getSettings()->update(['timezone' => 'America/Denver']);

    expect(TimeZones::preselected())->toBe('America/Denver');
    $this->get(route('register'))->assertOk()->assertSee('<option value="America/Denver" selected', false);
});

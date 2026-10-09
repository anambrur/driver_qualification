<?php

use App\Models\SiteSetting;
use Tests\Support\Actors;

/*
| SET-04: the Google Analytics id is printed inside a JS string on every page (welcome +
| main layout). Only accept GA4 measurement ids, and JSON-encode the value in the script so a
| bad stored value can't break or escape the string. Also guards the Tawk id extraction.
*/

function storeGaId(?string $id): void
{
    SiteSetting::getSettings()->update(['google_analytics_id' => $id]);
}

describe('validation', function () {
    it('rejects GA ids that are not GA4 measurement ids', function (string $id) {
        $this->actingAs(Actors::superAdmin())
            ->from(route('admin.settings.site.index'))
            ->put(route('admin.settings.site.update'), ['site_name' => 'DriverFilesHub', 'google_analytics_id' => $id])
            ->assertSessionHasErrors('google_analytics_id');

        expect(SiteSetting::first()->google_analytics_id)->toBeNull();
    })->with([
        'quote breakout' => ["G-1');alert(1);//"],
        'trailing backslash' => ['G-ABC\\'],
        'script close' => ['G-1</script><script>alert(1)</script>'],
        'not a GA id' => ['hello'],
        'lowercase' => ['g-abc123'],
    ]);

    it('accepts a GA4 measurement id and an empty value', function () {
        $admin = Actors::superAdmin();

        $this->actingAs($admin)
            ->put(route('admin.settings.site.update'), ['site_name' => 'DriverFilesHub', 'google_analytics_id' => 'G-AB12CD34EF'])
            ->assertSessionHasNoErrors();
        expect(SiteSetting::first()->google_analytics_id)->toBe('G-AB12CD34EF');

        $this->actingAs($admin)
            ->put(route('admin.settings.site.update'), ['site_name' => 'DriverFilesHub', 'google_analytics_id' => ''])
            ->assertSessionHasNoErrors();
        expect(SiteSetting::first()->google_analytics_id)->toBeNull();
    });
});

describe('rendering', function () {
    it('JSON-encodes the GA id inside the gtag script on the welcome page', function () {
        storeGaId('G-AB12CD34EF');

        $this->get('/')
            ->assertOk()
            ->assertSee('gtag(\'config\', "G-AB12CD34EF");', false);
    });

    it('cannot break the gtag string with a legacy stored value', function () {
        // A value saved before validation existed: a trailing backslash used to escape the closing quote.
        storeGaId('G-ABC\\');

        $this->get('/')
            ->assertOk()
            ->assertSee('gtag(\'config\', "G-ABC\\\\");', false)
            ->assertDontSee("'G-ABC\\'", false);
    });

    it('JSON-encodes the GA id in the admin layout', function () {
        storeGaId('G-AB12CD34EF');

        $this->actingAs(Actors::superAdmin())
            ->get(route('admin.settings.site.index'))
            ->assertOk()
            ->assertSee('gtag(\'config\', "G-AB12CD34EF");', false);
    });
});

describe('tawk.to ids', function () {
    it('rejects an embed url whose ids contain characters outside [A-Za-z0-9_-]', function () {
        $code = <<<'HTML'
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){var s1=document.createElement("script");s1.src='https://embed.tawk.to/abc"+alert(1)+"/default';})();
</script>
HTML;

        $this->actingAs(Actors::superAdmin())
            ->from(route('admin.settings.tawk.index'))
            ->put(route('admin.settings.tawk.update'), ['tawk_enabled' => '1', 'tawk_widget_code' => $code])
            ->assertSessionHasErrors('tawk_widget_code');

        expect(SiteSetting::first()->tawk_property_id)->toBeNull();
    });

    it('JSON-encodes the stored ids when rendering the widget', function () {
        SiteSetting::getSettings()->update(['tawk_enabled' => true, 'tawk_property_id' => 'prop_1', 'tawk_widget_id' => 'default']);

        $this->get('/')
            ->assertOk()
            ->assertSee('\'https://embed.tawk.to/\' + "prop_1" + \'/\' + "default"', false);
    });
});

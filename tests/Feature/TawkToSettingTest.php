<?php

use App\Models\Company;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function createTawkAdminUser(): User
{
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

    Permission::firstOrCreate(['name' => 'settings.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'settings.edit', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $user->assignRole('super-admin');
    $user->givePermissionTo(['settings.view', 'settings.edit']);

    return $user;
}

function validTawkWidgetCode(
    string $propertyId = '5f8a1b2c3d4e5f6a7b8c9d0e',
    string $widgetId = 'default',
    string $extraJavaScript = '',
): string {
    return <<<HTML
<!--Start of Tawk.to Script-->
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/{$propertyId}/{$widgetId}';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
{$extraJavaScript}
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<!--End of Tawk.to Script-->
HTML;
}

function enableTawkSettings(string $propertyId = '5f8a1b2c3d4e5f6a7b8c9d0e', string $widgetId = 'default'): SiteSetting
{
    Cache::forget('site_settings');

    $setting = SiteSetting::getSettings();
    $setting->update([
        'tawk_enabled' => true,
        'tawk_property_id' => $propertyId,
        'tawk_widget_id' => $widgetId,
    ]);

    Cache::forget('site_settings');

    return $setting->fresh();
}

function disableTawkSettings(): SiteSetting
{
    Cache::forget('site_settings');

    $setting = SiteSetting::getSettings();
    $setting->update([
        'tawk_enabled' => false,
        'tawk_property_id' => '5f8a1b2c3d4e5f6a7b8c9d0e',
        'tawk_widget_id' => 'default',
    ]);

    Cache::forget('site_settings');

    return $setting->fresh();
}

test('authorized admin can view tawk settings page', function () {
    $admin = createTawkAdminUser();

    $this->actingAs($admin)
        ->get(route('admin.settings.tawk.index'))
        ->assertOk()
        ->assertSee('Tawk.to Chat Settings', false);
});

test('authorized admin can update tawk settings', function () {
    $admin = createTawkAdminUser();
    $widgetCode = validTawkWidgetCode(widgetId: '1abc2def');

    $response = $this->actingAs($admin)->put(route('admin.settings.tawk.update'), [
        'tawk_enabled' => '1',
        'tawk_widget_code' => $widgetCode,
    ]);

    $response->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('site_settings', [
        'tawk_enabled' => 1,
        'tawk_property_id' => '5f8a1b2c3d4e5f6a7b8c9d0e',
        'tawk_widget_id' => '1abc2def',
    ]);

    expect(SiteSetting::first()->tawk_widget_code)->toBe($widgetCode);

    $storedValue = DB::table('site_settings')->value('tawk_widget_code');

    expect($storedValue)
        ->not->toBe($widgetCode)
        ->not->toContain('embed.tawk.to');
});

test('tawk settings reject a non-tawk embed domain', function () {
    $admin = createTawkAdminUser();
    $widgetCode = str_replace('embed.tawk.to', 'malicious.example', validTawkWidgetCode());

    $this->actingAs($admin)->from(route('admin.settings.tawk.index'))
        ->put(route('admin.settings.tawk.update'), [
            'tawk_enabled' => '1',
            'tawk_widget_code' => $widgetCode,
        ])
        ->assertRedirect(route('admin.settings.tawk.index'))
        ->assertSessionHasErrors(['tawk_widget_code']);
});

test('tawk settings reject multiple embed urls', function () {
    $admin = createTawkAdminUser();
    $widgetCode = str_replace(
        '</script>',
        "var duplicate='https://embed.tawk.to/5f8a1b2c3d4e5f6a7b8c9d0e/duplicate';\n</script>",
        validTawkWidgetCode(),
    );

    $this->actingAs($admin)->from(route('admin.settings.tawk.index'))
        ->put(route('admin.settings.tawk.update'), [
            'tawk_enabled' => '1',
            'tawk_widget_code' => $widgetCode,
        ])
        ->assertRedirect(route('admin.settings.tawk.index'))
        ->assertSessionHasErrors(['tawk_widget_code']);
});

test('tawk settings reject incomplete widget markup', function () {
    $admin = createTawkAdminUser();

    $this->actingAs($admin)->from(route('admin.settings.tawk.index'))
        ->put(route('admin.settings.tawk.update'), [
            'tawk_enabled' => '1',
            'tawk_widget_code' => "<script>var src='https://embed.tawk.to/property/default';</script>",
        ])
        ->assertRedirect(route('admin.settings.tawk.index'))
        ->assertSessionHasErrors(['tawk_widget_code']);
});

test('disabling chat preserves the encrypted widget code', function () {
    $admin = createTawkAdminUser();
    $widgetCode = validTawkWidgetCode();

    $this->actingAs($admin)->put(route('admin.settings.tawk.update'), [
        'tawk_enabled' => '1',
        'tawk_widget_code' => $widgetCode,
    ])->assertSessionHasNoErrors();

    $encryptedValue = DB::table('site_settings')->value('tawk_widget_code');

    $this->actingAs($admin)->put(route('admin.settings.tawk.update'), [
        'tawk_enabled' => '0',
        'tawk_widget_code' => '',
    ])->assertSessionHasNoErrors();

    $setting = SiteSetting::first();

    expect($setting->tawk_enabled)->toBeFalse()
        ->and($setting->tawk_widget_code)->toBe($widgetCode)
        ->and(DB::table('site_settings')->value('tawk_widget_code'))->toBe($encryptedValue);
});

test('stored widget javascript is never rendered directly', function () {
    $admin = createTawkAdminUser();
    $widgetCode = validTawkWidgetCode(extraJavaScript: 'window.__stored_tawk_payload = true;');

    $this->actingAs($admin)->put(route('admin.settings.tawk.update'), [
        'tawk_enabled' => '1',
        'tawk_widget_code' => $widgetCode,
    ])->assertSessionHasNoErrors();

    $this->get('/')
        ->assertOk()
        ->assertSee('embed.tawk.to', false)
        ->assertDontSee('__stored_tawk_payload', false);
});

test('welcome page renders tawk widget when enabled', function () {
    enableTawkSettings();

    $this->get('/')
        ->assertOk()
        ->assertSee('embed.tawk.to', false)
        ->assertSee('5f8a1b2c3d4e5f6a7b8c9d0e', false)
        ->assertSee('default', false);
});

test('welcome page does not render tawk widget when disabled', function () {
    disableTawkSettings();

    $this->get('/')
        ->assertOk()
        ->assertDontSee('embed.tawk.to', false);
});

test('welcome page does not render tawk widget when ids are incomplete', function () {
    Cache::forget('site_settings');

    $setting = SiteSetting::getSettings();
    $setting->update([
        'tawk_enabled' => true,
        'tawk_property_id' => '5f8a1b2c3d4e5f6a7b8c9d0e',
        'tawk_widget_id' => null,
    ]);

    Cache::forget('site_settings');

    $this->get('/')
        ->assertOk()
        ->assertDontSee('embed.tawk.to', false);
});

test('application form page renders tawk widget when enabled', function () {
    enableTawkSettings();

    $admin = createTawkAdminUser();
    $company = Company::create([
        'user_id' => $admin->id,
        'company_name' => 'Public Apply Co',
        'slug' => 'public-apply-co',
        'email' => 'apply@test.com',
        'status' => 'active',
    ]);

    $this->get(route('application.form', $company->slug))
        ->assertOk()
        ->assertSee('embed.tawk.to', false);
});

test('login and register pages render tawk widget when enabled', function () {
    enableTawkSettings();

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('embed.tawk.to', false);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee('embed.tawk.to', false);
});

test('guest password reset page renders tawk widget when enabled', function () {
    enableTawkSettings();

    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('embed.tawk.to', false);
});

test('authenticated email verification page does not render tawk widget', function () {
    enableTawkSettings();

    $user = User::factory()->unverified()->create([
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertDontSee('embed.tawk.to', false);
});

test('admin dashboard does not render tawk widget', function () {
    enableTawkSettings();

    $admin = createTawkAdminUser();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertDontSee('embed.tawk.to', false);
});

<?php

use Tests\Feature\Modules\DriverCompliance\DriverCompliance;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Feature\Modules\FleetCompliance\FleetCompliance;
use Tests\Support\Actors;

/*
| UI-13 (phase 2, batch 5b): no layout loads SweetAlert2 or the Font Awesome kit any more. Alerts and
| confirms run on <x-ui.confirm-dialog> (resources/js/dialogs.js), icons are Lucide.
*/

function assertNoSweetAlertOrFontAwesome(string $html): void
{
    expect($html)->not->toContain('sweetalert2')
        ->not->toContain('Swal.')
        ->not->toContain('swal2-')
        ->not->toContain('kit.fontawesome.com')
        ->not->toContain('class="fas ')
        ->not->toMatch('/\bfa-[a-z]/')
        ->toContain('id="confirm-dialog"');
}

it('renders the company pages without SweetAlert or Font Awesome', function (string $route) {
    $owner = Actors::companyOwner();
    $url = $route === 'admin.settings.company.edit' ? route($route, $owner->company_id) : route($route);

    assertNoSweetAlertOrFontAwesome($this->actingAs($owner)->get($url)->assertOk()->getContent());
})->with([
    'dashboard', 'admin.roles.index', 'admin.roles.create', 'admin.settings.company.edit', 'admin.settings.policy.pdf',
    'admin.driver.index', 'admin.settings.document-types.index',
]);

it('renders the compliance dashboards without SweetAlert or Font Awesome', function () {
    [$driverOwner] = DriverCompliance::tenantWithDriver();
    assertNoSweetAlertOrFontAwesome($this->actingAs($driverOwner)->get(route('admin.compliance.drivers'))->assertOk()->getContent());

    [$fleetOwner] = FleetCompliance::tenantWithAsset();
    assertNoSweetAlertOrFontAwesome($this->actingAs($fleetOwner)->get(route('admin.compliance.fleet'))->assertOk()->getContent());
});

it('renders the driver page and every admin wizard step without SweetAlert or Font Awesome', function () {
    [$owner, $driver] = DriverAdmin::tenantWithDriver();

    foreach ([
        'admin.driver.show', 'admin.driver.license', 'admin.driver.medical.card', 'admin.driver.forfeiture', 'admin.driver.violation',
        'admin.driver.alcohol.and.drug.test', 'admin.driver.fmcsa.consent', 'admin.driver.psp',
        'admin.driver.alcohol.and.drug.test.policy', 'admin.driver.general.work.policy',
    ] as $route) {
        $html = $this->actingAs($owner)->get(route($route, $driver->id))->assertOk()->getContent();
        assertNoSweetAlertOrFontAwesome($html);
    }
});

it('renders the platform pages without SweetAlert', function () {
    $html = $this->actingAs(Actors::platformOwner(), 'platform')->get(route('platform.dashboard'))->assertOk()->getContent();

    assertNoSweetAlertOrFontAwesome($html);
});

it('opens the fleet upload form in an x-ui dialog with the same field ids and names', function () {
    [$owner] = FleetCompliance::tenantWithAsset();

    $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk()
        ->assertSee('<dialog id="uploadDocumentModal"', false)
        ->assertSee(['id="uploadDocumentForm"', 'id="upload_document_type_id" name="document_type_id"', 'id="upload_asset_type" name="asset_type"',
            'id="upload_asset_id" name="asset_id"', 'id="upload_company_id" name="company_id"', 'id="upload_selected_asset" name="selected_asset"',
            'name="upload_to_all"', 'name="expiry_date"', 'name="description"', 'id="upload_file" name="file"', 'id="uploadSubmitBtn"'], false)
        ->assertSee('window.openUploadModal = ', false)
        ->assertDontSee('id="toast-container"', false);
});

it('confirms the hire decision as plain text, never as HTML', function () {
    [$owner, $driver] = DriverAdmin::tenantWithDriver(['status' => 'pending']);

    $this->actingAs($owner)->get(route('admin.driver.show', $driver->id))->assertOk()
        ->assertSee('showAppConfirm(text, options)', false)
        ->assertDontSee('html: summary', false);
});

it('still forbids the compliance dashboards and roles without permission', function () {
    $member = Actors::memberWithPermissions(Actors::company(), ['drivers.view']);

    $this->actingAs($member)->get(route('admin.compliance.fleet'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.compliance.drivers'))->assertForbidden();
    $this->actingAs($member)->get(route('admin.roles.index'))->assertForbidden();
});

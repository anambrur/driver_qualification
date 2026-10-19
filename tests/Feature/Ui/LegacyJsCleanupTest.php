<?php

use App\Models\DriverDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\DriverCompliance\DriverCompliance;
use Tests\Feature\Modules\Drivers\DriverAdmin;
use Tests\Feature\Modules\Maintenance\Maintenance;
use Tests\Feature\Modules\PublicApplication\Applicant;
use Tests\Support\Actors;

/*
| UI-31 (phase 2, batch 9b): no inline event handlers outside the x-ui dialog component. Buttons
| open and close dialogs with data-dialog-open / data-dialog-close, x-application.upload removes a
| preview with data-remove-preview (all delegated in resources/js/app.js), the upload modals use
| data-upload-action, and the driver page uses data-action like the compliance dashboards. The
| send-reminder script no longer falls back to the native confirm(). The driver page's CDL row has
| no document type, so its upload and reminder buttons always failed; it now links to the license step.
*/

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

/**
 * The page without the x-ui dialog's own two handlers (overlay click and the header close button).
 */
function withoutDialogHandlers(string $html): string
{
    return str_replace(
        ['onclick="if (event.target === this) this.close()"', 'aria-label="Close dialog" onclick="this.closest(\'dialog\').close()"'],
        '',
        $html,
    );
}

it('renders the tenant pages without inline handlers', function (string $route) {
    [$owner] = Maintenance::tenantWithVehicle();

    $html = $this->actingAs($owner)->get(route($route))->assertOk()->getContent();

    expect(withoutDialogHandlers($html))->not->toMatch('/\son(click|change|submit|input)=/');
})->with(['admin.settings.document-types.index', 'admin.maintenance-schedule.index', 'admin.service-log.index', 'admin.vehicle.index', 'admin.trailer.index', 'admin.asset-group.index', 'profile.edit', 'admin.compliance.drivers', 'admin.compliance.fleet']);

it('renders the platform lookup pages without inline handlers', function (string $route) {
    $html = $this->actingAs(Actors::platformOwner(), 'platform')->get(route($route))->assertOk()->getContent();

    expect(withoutDialogHandlers($html))->not->toMatch('/\son(click|change|submit|input)=/')
        ->toContain('data-dialog-close');
})->with(['platform.lookups.vehicle-types.index', 'platform.lookups.document-types.index']);

it('opens and closes the dialogs through data attributes', function () {
    [$owner] = Maintenance::tenantWithVehicle();

    $this->actingAs($owner)->get(route('profile.edit'))->assertOk()
        ->assertSee('data-dialog-open="confirm-user-deletion"', false)
        ->assertSee('data-dialog-close', false);

    expect(File::get(resource_path('js/app.js')))
        ->toContain('closest("[data-dialog-open], [data-dialog-close], [data-remove-preview]")')
        ->toContain('document.getElementById(button.dataset.dialogOpen)?.showModal();')
        ->toContain('button.closest("dialog")?.close();')
        ->toContain('window.removePreview?.(button.dataset.removePreview);');
});

it('wires the driver page actions through data-action', function () {
    [$owner, $driver] = DriverCompliance::tenantWithDriver(['status' => 'pending']);
    $type = DriverCompliance::documentType(['name' => 'Medical Card']);
    DriverCompliance::document($driver, $type);

    $html = $this->actingAs($owner)->get(route('admin.driver.show', $driver->id))->assertOk()->getContent();

    expect(withoutDialogHandlers($html))->not->toMatch('/\son(click|change|submit|input)=/')
        ->and($html)->toContain('data-dialog-open="hire-dialog"')
        ->toContain('data-dialog-open="not-hire-dialog"')
        ->toMatch('/data-action="upload-document" data-id="'.$driver->id.'" data-type-id="'.$type->id.'" data-asset-type="driver"/')
        ->toMatch('/data-action="send-reminder" data-id="'.$driver->id.'" data-type-id="'.$type->id.'" data-asset-type="driver"/')
        ->toContain('data-action="delete-document" data-document-id="')
        ->toContain("case 'upload-document':")
        ->toContain('data-upload-action="remove-file"')
        ->toContain('data-upload-action="close"')
        ->toContain("dialog.addEventListener('click'");
});

it('sends the CDL row to the license step instead of an upload with no document type', function () {
    [$owner, $driver] = DriverCompliance::tenantWithDriver();

    $html = $this->actingAs($owner)->get(route('admin.driver.show', $driver->id))->assertOk()->getContent();

    expect($html)->toContain('href="'.route('admin.driver.license', ['driver_id' => $driver->id, 'edit' => '1']).'"')
        ->not->toContain('data-type-id=""')
        ->not->toContain('data-type-id ');
});

it('asks before sending a reminder only in the shared confirm dialog', function () {
    expect(File::get(resource_path('views/admin/compliance/partials/send-reminder-script.blade.php')))
        ->toContain('showAppConfirm(confirmMessage, {')
        ->not->toMatch('/(?<![\w.])confirm\(/')
        ->not->toContain('typeof showAppConfirm');
});

it('removes an upload preview through data-remove-preview', function () {
    Applicant::fakeOtp();
    $company = Applicant::company();
    $driver = Applicant::draft($company);
    DriverDocument::create(['driver_id' => $driver->id]);

    $this->withSession(Applicant::session($driver))
        ->get(route('public.application.step2', ['slug' => $company->slug, 'driver_id' => $driver->id]))
        ->assertOk()
        ->assertSee('data-remove-preview="license_front"', false)
        ->assertSee('data-remove-preview="license_back"', false)
        ->assertDontSee('onclick="removePreview', false);

    $this->flushSession();
    [$owner, $admin] = DriverAdmin::tenantWithDriver();
    $this->actingAs($owner)->get(route('admin.driver.medical.card', $admin->id))
        ->assertOk()
        ->assertSee('data-remove-preview="medical_card"', false)
        ->assertDontSee('onclick="removePreview', false);
});

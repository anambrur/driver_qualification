<?php

use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\DriverCompliance\DriverCompliance;
use Tests\Feature\Modules\FleetCompliance\FleetCompliance;
use Tests\Support\Actors;

/*
| UI-15 (phase 2, batch 8a): the driver and fleet compliance dashboards on x-ui. The details and
| image preview modals are native x-ui dialogs, the page buttons use data-action delegation, and
| the shared JS lives in admin/compliance/partials/page-script. Routes, permissions and the
| details JSON are unchanged.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

/**
 * The page's own markup: the layout and the x-ui dialog component carry their own inline handlers.
 */
function compliancePageMarkup(string $html): string
{
    $start = strpos($html, 'Compliance Dashboard</h1>');
    $end = strpos($html, '<dialog id="uploadDocumentModal"');

    return str_replace(
        ['onclick="if (event.target === this) this.close()"', 'onclick="this.closest(\'dialog\').close()"'],
        '',
        substr($html, $start, $end - $start),
    );
}

function assertBatch8aUi(string $html): void
{
    expect($html)->toContain('class="stat-icon')
        ->toContain('<dialog id="imagePreviewModal"')
        ->toContain('id="imagePreviewImage"')
        ->toContain('function escapeHtml(')
        ->toContain('case \'show-details\':')
        ->not->toContain('shadow-theme-')
        ->not->toContain('dark:text-white/90')
        ->not->toContain('insertAdjacentHTML')
        ->not->toMatch('/\b(text|bg|border)-(zinc|red|amber|emerald|blue|purple)-\d/');

    expect(compliancePageMarkup($html))->not->toContain('onclick=');
}

describe('driver compliance dashboard', function () {
    it('renders drivers, documents and dialogs on x-ui', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver(['first_name' => 'Jordan', 'last_name' => 'Lee']);
        DriverCompliance::document($driver, DriverCompliance::documentType(['name' => 'Medical Card']), ['expiry_date' => now()->addDays(10)->toDateString()]);
        DriverCompliance::documentType(['name' => 'Road Test']);

        $html = $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk()
            ->assertSee(['Driver Compliance Dashboard', 'Jordan Lee', 'Medical Card', 'Expiring Soon', 'Road Test', 'Missing', 'Drivers Need Attention'])
            ->assertSee('<dialog id="driverDetailsModal"', false)
            ->assertSee('data-action="show-details" data-kind="driver" data-id="'.$driver->id.'"', false)
            ->assertSee('data-action="upload-document"', false)
            ->assertSee('data-action="send-reminder"', false)
            ->assertSee('data-asset-type="driver"', false)
            ->assertDontSee('Reports &amp; Records', false)
            ->getContent();

        assertBatch8aUi($html);
    });

    it('shows the empty state without drivers', function () {
        $this->actingAs(Actors::companyOwner())->get(route('admin.compliance.drivers'))->assertOk()
            ->assertSee(['No Drivers Found', 'Add First Driver'])
            ->assertSee('class="empty border', false);
    });

    it('is forbidden without drivers.dashboard', function () {
        $member = Actors::memberWithPermissions(Actors::company(), ['drivers.view']);

        $this->actingAs($member)->get(route('admin.compliance.drivers'))->assertForbidden();
    });
});

describe('fleet compliance dashboard', function () {
    it('renders vehicles, trailers and dialogs on x-ui', function () {
        [$owner, $vehicle] = FleetCompliance::tenantWithAsset('vehicle', ['unit_no' => 'V-101']);
        $trailer = FleetCompliance::assetOf($owner, 'trailer', ['unit_no' => 'T-201']);
        FleetCompliance::document($vehicle, FleetCompliance::documentType('vehicle', ['name' => 'Registration']), ['expiry_date' => now()->addDays(12)->toDateString()]);
        FleetCompliance::document($trailer, FleetCompliance::documentType('trailer', ['name' => 'Annual Inspection']));

        $html = $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk()
            ->assertSee(['Fleet Compliance Dashboard', '#V-101', '#T-201', 'Registration', 'Annual Inspection', 'Vehicles Need Attention'])
            ->assertSee('<dialog id="vehicleDetailsModal"', false)
            ->assertSee('data-action="show-details" data-kind="vehicle" data-id="'.$vehicle->id.'"', false)
            ->assertSee('data-action="show-details" data-kind="trailer" data-id="'.$trailer->id.'"', false)
            ->assertSee('data-asset-type="vehicle"', false)
            ->getContent();

        assertBatch8aUi($html);
    });

    it('shows the empty states without vehicles or trailers', function () {
        $this->actingAs(Actors::companyOwner())->get(route('admin.compliance.fleet'))->assertOk()
            ->assertSee(['No Vehicles Found', 'No Trailers Found']);
    });

    it('is forbidden without fleets.dashboard', function () {
        $member = Actors::memberWithPermissions(Actors::company(), ['vehicles.view']);

        $this->actingAs($member)->get(route('admin.compliance.fleet'))->assertForbidden();
    });
});

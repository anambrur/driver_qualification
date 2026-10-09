<?php

use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\DriverCompliance\DriverCompliance;

/*
| DCMP-08: the dashboard's details modal and the upload modal's driver dropdown built HTML
| from JSON with template literals and innerHTML, unescaped. Name, email, phone and licence
| number come from public applicants, and the description from any tenant user (a super-admin
| sees every tenant's). They now go through escapeHtml() / new Option(); the image preview
| button uses data-* attributes and a listener instead of an inline onclick with the type name.
*/

beforeEach(function () {
    Storage::fake('local');
});

const DCMP_PAYLOAD = '<img src=x onerror=alert(1)>';

describe('DCMP-08: driver data is rendered as text', function () {
    it('escapes the driver name in the server-rendered list', function () {
        [$owner] = DriverCompliance::tenantWithDriver(['first_name' => DCMP_PAYLOAD]);

        $html = $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk()->getContent();

        expect($html)->not->toContain(DCMP_PAYLOAD)->and($html)->toContain(e(DCMP_PAYLOAD));
    });

    it('escapes every applicant- or tenant-controlled field in the details modal', function () {
        [$owner] = DriverCompliance::tenantWithDriver();

        $html = $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk()->getContent();

        foreach (['doc.type_name', 'doc.description', "driver.email || 'N/A'", "driver.phone || 'N/A'", "driver.license_number || 'N/A'", "driver.license_state || 'N/A'", "driver.status || 'N/A'", 'doc'] as $expr) {
            expect($html)->not->toContain('${'.$expr.'}');
        }
        expect($html)->toContain('function escapeHtml(')
            ->and($html)->toContain('${escapeHtml(doc.description)}')
            ->and($html)->toContain("\${escapeHtml(driver.email || 'N/A')}");
    });

    it('does not put the document type name in an inline onclick', function () {
        [$owner] = DriverCompliance::tenantWithDriver();

        $html = $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk()->getContent();

        expect($html)->not->toContain("onclick=\"previewImage(")
            ->and($html)->toContain('data-action="preview-document"');
    });

    it('builds the upload dropdown with text options, not innerHTML', function () {
        [$owner] = DriverCompliance::tenantWithDriver();

        $html = $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk()->getContent();

        expect($html)->not->toContain('${asset.full_name}')
            ->and($html)->toContain('new Option(');
    });
});

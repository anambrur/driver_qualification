<?php

use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\FleetCompliance\FleetCompliance;

/*
| FCMP-06 (found 2026-10-10): the dashboard's details modal and the upload modal's asset
| dropdown built HTML from JSON with template literals and innerHTML, unescaped. Make, model,
| VIN, unit number and the document description come from any tenant user, and a super-admin
| sees every tenant's. They now go through escapeHtml() / new Option(); the image preview
| button uses data-* attributes and a listener instead of an inline onclick with the type name.
*/

beforeEach(function () {
    Storage::fake('local');
});

const FCMP_PAYLOAD = '<img src=x onerror=alert(1)>';

describe('FCMP-06: vehicle and trailer data is rendered as text', function () {
    it('escapes the unit number and driver name in the server-rendered list', function (string $assetType) {
        [$owner] = FleetCompliance::tenantWithAsset($assetType, ['unit_no' => FCMP_PAYLOAD]);

        $html = $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk()->getContent();

        expect($html)->not->toContain(FCMP_PAYLOAD)->and($html)->toContain(e(FCMP_PAYLOAD));
    })->with(['vehicle', 'trailer']);

    it('escapes every tenant-controlled field in the details modal', function () {
        [$owner] = FleetCompliance::tenantWithAsset();

        $html = $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk()->getContent();

        foreach (['doc.type_name', 'doc.description', 'doc', 'vehicle.make', 'vehicle.model', 'vehicle.vin', 'vehicle.year', 'trailer.make', 'trailer.model', 'trailer.vin', 'trailer.year'] as $expr) {
            expect($html)->not->toContain('${'.$expr.'}');
        }
        expect($html)->toContain('function escapeHtml(')
            ->and($html)->toContain('${escapeHtml(doc.description)}')
            ->and($html)->toContain('${escapeHtml(vehicle.vin)}')
            ->and($html)->toContain('${escapeHtml(trailer.vin)}');
    });

    it('does not put the document type name in an inline onclick', function () {
        [$owner] = FleetCompliance::tenantWithAsset();

        $html = $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk()->getContent();

        expect($html)->not->toContain('onclick="previewImage(')
            ->and($html)->toContain('data-action="preview-document"');
    });

    it('builds the upload dropdown with text options, not innerHTML', function () {
        [$owner] = FleetCompliance::tenantWithAsset();

        $html = $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk()->getContent();

        expect($html)->not->toContain('${asset.unit_no}')
            ->and($html)->toContain('new Option(');
    });
});

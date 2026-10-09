<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\FleetCompliance\FleetCompliance;
use Tests\Support\Actors;
use Tests\Support\Perf;

/*
| FCMP-03: the upload modal's asset lists ran one `exists` query per vehicle/trailer, and the
| dashboard loads every asset with its documents.
|
| FCMP-04: ComplianceDashboardController re-implements the compliance math. It was checked for
| the DCMP-03 bug (warning unreachable): it doesn't have it. But the view/download routes took
| any {assetType} other than "vehicle" as a trailer, so /documents/5/anything/view served
| trailer document 5.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

/**
 * The processed rows the dashboard hands to its view for one asset type.
 *
 * @return array<int, array<string, mixed>>
 */
function fcmpDashboardRows($test, User $user, string $assetType): array
{
    return $test->actingAs($user)->get(route('admin.compliance.fleet'))->assertOk()->viewData($assetType.'s');
}

describe('FCMP-04: compliance status', function () {
    it('counts an asset whose only problem is an expiring document as Warning', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        FleetCompliance::document($asset, FleetCompliance::documentType($assetType), ['expiry_date' => now()->addDays(10)->toDateString()]);
        FleetCompliance::document($asset, FleetCompliance::documentType($assetType), ['expiry_date' => now()->addYear()->toDateString()]);

        $row = fcmpDashboardRows($this, $owner, $assetType)[0];

        expect($row['compliance_status'])->toBe('warning')
            ->and($row['compliant_docs'])->toBe(1)
            ->and($row['total_docs'])->toBe(2);
    })->with(['vehicle', 'trailer']);

    it('is danger when a document is missing or expired, even if another one is only expiring', function (string $assetType, ?string $otherExpiry) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        FleetCompliance::document($asset, FleetCompliance::documentType($assetType), ['expiry_date' => now()->addDays(10)->toDateString()]);
        $other = FleetCompliance::documentType($assetType);
        if ($otherExpiry) {
            FleetCompliance::document($asset, $other, ['expiry_date' => $otherExpiry]);
        }

        expect(fcmpDashboardRows($this, $owner, $assetType)[0]['compliance_status'])->toBe('danger');
    })->with(['vehicle', 'trailer'])->with([
        'missing' => [null],
        'expired' => [fn () => now()->subDay()->toDateString()],
        'expires today' => [fn () => now()->toDateString()],
    ]);

    it('is compliant at 100% when every document is valid', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        FleetCompliance::document($asset, FleetCompliance::documentType($assetType), ['expiry_date' => now()->addDays(31)->toDateString()]);

        $row = fcmpDashboardRows($this, $owner, $assetType)[0];

        expect($row['compliance_status'])->toBe('compliant')->and($row['compliance_percentage'])->toBe('100%');
    })->with(['vehicle', 'trailer']);

    it('returns warning from the details endpoint too', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        FleetCompliance::document($asset, FleetCompliance::documentType($assetType), ['expiry_date' => now()->addDays(5)->toDateString()]);

        $this->actingAs($owner)
            ->getJson(route('admin.compliance.'.$assetType.'.details', $asset->id))
            ->assertOk()
            ->assertJsonPath($assetType.'.compliance_status', 'warning');
    })->with(['vehicle', 'trailer']);

    it('returns 404 for an asset type other than vehicle or trailer', function (string $route) {
        [$owner, $trailer] = FleetCompliance::tenantWithAsset('trailer');
        $document = FleetCompliance::document($trailer, FleetCompliance::documentType('trailer'));

        $this->actingAs($owner)->get(route($route, [$document->id, 'trailer']))->assertOk();
        $this->actingAs($owner)->get(route($route, [$document->id, 'anything']))->assertNotFound();
    })->with(['admin.compliance.documents.view', 'admin.compliance.documents.download']);
});

describe('FCMP-03: query budget', function () {
    it('lists the upload modal\'s assets in a constant number of queries', function (string $assetType, string $route) {
        $owner = Actors::companyOwner();
        $type = FleetCompliance::documentType($assetType);

        Perf::assertConstantQueries(
            function (int $n) use ($owner, $assetType, $type) {
                for ($i = 0; $i < $n; $i++) {
                    $asset = FleetCompliance::assetOf($owner, $assetType);
                    if ($i % 2 === 0) {
                        FleetCompliance::document($asset, $type);
                    }
                }
            },
            fn () => $this->actingAs($owner)->getJson(route($route, ['document_type_id' => $type->id]))->assertOk(),
        );
    })->with([
        'vehicles' => ['vehicle', 'admin.compliance.vehicles.list'],
        'trailers' => ['trailer', 'admin.compliance.trailers.list'],
    ]);

    it('still flags which assets already have the document', function (string $assetType, string $route) {
        [$owner, $with] = FleetCompliance::tenantWithAsset($assetType, ['unit_no' => 'A1']);
        $without = FleetCompliance::assetOf($owner, $assetType, ['unit_no' => 'B2']);
        $type = FleetCompliance::documentType($assetType);
        FleetCompliance::document($with, $type);
        FleetCompliance::document($without, FleetCompliance::documentType($assetType));

        $this->actingAs($owner)
            ->getJson(route($route, ['document_type_id' => $type->id]))
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'assets' => [
                    ['id' => $with->id, 'unit_no' => 'A1', 'has_document' => true],
                    ['id' => $without->id, 'unit_no' => 'B2', 'has_document' => false],
                ],
                'document_type_name' => $type->name,
                'total' => 2,
            ]);
    })->with([
        'vehicles' => ['vehicle', 'admin.compliance.vehicles.list'],
        'trailers' => ['trailer', 'admin.compliance.trailers.list'],
    ]);

    it('loads the dashboard in a constant number of queries', function () {
        $owner = Actors::companyOwner();
        $vehicleType = FleetCompliance::documentType('vehicle');
        $trailerType = FleetCompliance::documentType('trailer');

        Perf::assertConstantQueries(
            function (int $n) use ($owner, $vehicleType, $trailerType) {
                for ($i = 0; $i < $n; $i++) {
                    FleetCompliance::document(FleetCompliance::assetOf($owner, 'vehicle'), $vehicleType);
                    FleetCompliance::document(FleetCompliance::assetOf($owner, 'trailer'), $trailerType);
                }
            },
            fn () => $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk(),
        );
    });
});

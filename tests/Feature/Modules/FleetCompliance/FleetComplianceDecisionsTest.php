<?php

use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\FleetCompliance\FleetCompliance;
use Tests\Support\Actors;

/*
| Product decisions of 2026-10-11 (docs/testing/PRODUCT_DECISIONS.md):
| PO-01  the fleet dashboard paginates vehicles and trailers (25 per page, own page parameter
|        each, summary cards still count every asset).
| PO-02  "upload to all" works from the browser checkbox; a super-admin must pick one company,
|        tenants always use their own; soft-deleted assets are excluded.
| PO-22  fleet follow-ups: fleet document types only, separate Warning / Critical counts,
|        whole-number days, 422 (and no stored file) when "upload to all" has no assets.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

/**
 * @return array<string, mixed>
 */
function fcmpUploadAllPayload(string $assetType, int $documentTypeId, array $overrides = []): array
{
    return array_merge([
        'document_type_id' => $documentTypeId,
        'asset_type' => $assetType,
        'upload_to_all' => '1',
        'expiry_date' => now()->addYear()->toDateString(),
        'file' => FleetCompliance::pdf(),
    ], $overrides);
}

function fcmpUnitNos($page): array
{
    return collect($page->items())->pluck('unit_no')->all();
}

describe('PO-01: the fleet dashboard is paginated', function () {
    it('shows 25 assets per page, each list with its own page parameter', function (string $assetType) {
        $owner = Actors::companyOwner();
        for ($i = 1; $i <= 30; $i++) {
            FleetCompliance::assetOf($owner, $assetType, ['unit_no' => sprintf('U%02d', $i)]);
        }

        $first = $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk()->viewData($assetType.'s');
        $second = $this->actingAs($owner)->get(route('admin.compliance.fleet', [$assetType.'s_page' => 2]))->assertOk()->viewData($assetType.'s');

        expect($first->count())->toBe(25)
            ->and($first->total())->toBe(30)
            ->and(fcmpUnitNos($second))->toBe(['U26', 'U27', 'U28', 'U29', 'U30']);
    })->with(['vehicle', 'trailer']);

    it('keeps the other list on its own page', function () {
        $owner = Actors::companyOwner();
        for ($i = 1; $i <= 30; $i++) {
            FleetCompliance::assetOf($owner, 'vehicle', ['unit_no' => sprintf('V%02d', $i)]);
            FleetCompliance::assetOf($owner, 'trailer', ['unit_no' => sprintf('T%02d', $i)]);
        }

        $response = $this->actingAs($owner)->get(route('admin.compliance.fleet', ['vehicles_page' => 2]))->assertOk();

        expect(fcmpUnitNos($response->viewData('vehicles')))->toHaveCount(5)
            ->and($response->viewData('trailers')->currentPage())->toBe(1)
            // the trailers' "next" link keeps vehicles on page 2
            ->and($response->viewData('trailers')->nextPageUrl())->toContain('vehicles_page=2')->toContain('trailers_page=2');
    });

    it('counts the summary cards over every asset, not only the current page', function () {
        $owner = Actors::companyOwner();
        $type = FleetCompliance::documentType('vehicle');
        for ($i = 0; $i < 30; $i++) {
            $vehicle = FleetCompliance::assetOf($owner, 'vehicle');
            if ($i < 12) {
                FleetCompliance::document($vehicle, $type, ['expiry_date' => now()->addYear()->toDateString()]);
            } elseif ($i < 20) {
                FleetCompliance::document($vehicle, $type, ['expiry_date' => now()->addDays(10)->toDateString()]);
            }
            // the other 10 are missing the document
        }

        $response = $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk();

        expect($response->viewData('totalVehicles'))->toBe(30)
            ->and($response->viewData('totalCompliant'))->toBe(12)
            ->and($response->viewData('totalWarning'))->toBe(8)
            ->and($response->viewData('totalCritical'))->toBe(10)
            ->and($response->viewData('vehiclesNeedingAttention'))->toBe(18)
            ->and($response->viewData('vehicleCompliance'))->toBe(40.0);
    });

    it('counts the same statuses in SQL as on the rows', function () {
        $owner = Actors::companyOwner();
        $a = FleetCompliance::documentType('vehicle');
        $b = FleetCompliance::documentType('vehicle');
        $cases = [
            [now()->addYear(), now()->addYear()],    // compliant
            [now()->addDays(30), now()->addYear()],  // warning (30 days is still expiring)
            [now()->addDays(31), null],              // danger (b missing)
            [now(), now()->addYear()],               // danger (expires today)
            [null, null],                            // danger
        ];
        foreach ($cases as [$expiryA, $expiryB]) {
            $vehicle = FleetCompliance::assetOf($owner, 'vehicle');
            if ($expiryA) {
                FleetCompliance::document($vehicle, $a, ['expiry_date' => $expiryA->toDateString()]);
            }
            if ($expiryB) {
                FleetCompliance::document($vehicle, $b, ['expiry_date' => $expiryB->toDateString()]);
            }
        }

        $response = $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk();
        $rows = collect($response->viewData('vehicles')->items())->countBy('compliance_status');

        expect($rows->all())->toEqual(['compliant' => 1, 'warning' => 1, 'danger' => 3])
            ->and($response->viewData('totalCompliant'))->toBe(1)
            ->and($response->viewData('totalWarning'))->toBe(1)
            ->and($response->viewData('totalCritical'))->toBe(3);
    });

    it('shows and counts only the tenant\'s own assets', function () {
        $owner = Actors::companyOwner();
        FleetCompliance::assetOf($owner, 'vehicle', ['unit_no' => 'MINE']);
        [, $theirs] = FleetCompliance::tenantWithAsset('vehicle', ['unit_no' => 'THEIRS']);

        $response = $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk();

        expect(fcmpUnitNos($response->viewData('vehicles')))->toBe(['MINE'])
            ->and($response->viewData('totalVehicles'))->toBe(1);
    });

    it('renders the page links', function () {
        $owner = Actors::companyOwner();
        for ($i = 0; $i < 26; $i++) {
            FleetCompliance::assetOf($owner, 'vehicle');
        }

        $this->actingAs($owner)->get(route('admin.compliance.fleet'))
            ->assertOk()
            ->assertSee('vehicles_page=2', false);
    });
});

describe('PO-02: "upload to all" for fleet', function () {
    it('accepts the browser checkbox value', function (string $value) {
        [$owner] = FleetCompliance::tenantWithAsset('vehicle');
        $type = FleetCompliance::documentType('vehicle');

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), fcmpUploadAllPayload('vehicle', $type->id, ['upload_to_all' => $value]), FleetCompliance::json())
            ->assertOk()->assertJson(['success' => true, 'uploaded_count' => 1]);
    })->with(['on', '1']);

    it('gives the checkbox a value in the modal', function () {
        [$owner] = FleetCompliance::tenantWithAsset('vehicle');

        $this->actingAs($owner)->get(route('admin.compliance.fleet'))
            ->assertOk()
            ->assertSee('name="upload_to_all" value="1"', false)
            ->assertSee('id="upload_company_id" name="company_id"', false);
    });

    it('uploads, inside a support session, only to that company\'s assets')->todo('WP8b: read_write support session');

    it('ignores a company_id posted by a tenant', function () {
        [$owner, $mine] = FleetCompliance::tenantWithAsset('vehicle');
        [$other, $theirs] = FleetCompliance::tenantWithAsset('vehicle');
        $type = FleetCompliance::documentType('vehicle');

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), fcmpUploadAllPayload('vehicle', $type->id, ['company_id' => Actors::companyOf($other)->id]), FleetCompliance::json())
            ->assertOk()->assertJson(['uploaded_count' => 1]);

        expect(FleetCompliance::documentModel('vehicle')::withoutTenancy()->where('vehicle_id', $mine->id)->exists())->toBeTrue()
            ->and(FleetCompliance::documentModel('vehicle')::withoutTenancy()->where('vehicle_id', $theirs->id)->exists())->toBeFalse();
    });

    it('leaves soft-deleted assets out', function (string $assetType) {
        [$owner, $live] = FleetCompliance::tenantWithAsset($assetType);
        $deleted = FleetCompliance::assetOf($owner, $assetType);
        $deleted->delete();
        $type = FleetCompliance::documentType($assetType);

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), fcmpUploadAllPayload($assetType, $type->id), FleetCompliance::json())
            ->assertOk()->assertJson(['uploaded_count' => 1]);

        expect(FleetCompliance::documentModel($assetType)::withoutTenancy()->where($assetType.'_id', $deleted->id)->exists())->toBeFalse();
    })->with(['vehicle', 'trailer']);

});

describe('PO-22: fleet compliance follow-ups', function () {
    it('refuses a document type of another module', function (string $assetType, string $otherModule) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $wrong = FleetCompliance::documentType($otherModule);

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), FleetCompliance::payload($asset, $wrong), FleetCompliance::json())
            ->assertStatus(422);

        expect(FleetCompliance::documentModel($assetType)::withoutTenancy()->count())->toBe(0);
    })->with([
        'driver type on a vehicle' => ['vehicle', 'driver'],
        'trailer type on a vehicle' => ['vehicle', 'trailer'],
        'vehicle type on a trailer' => ['trailer', 'vehicle'],
    ]);

    it('returns 422 and stores no file when there are no assets to upload to', function (string $assetType) {
        $owner = Actors::companyOwner();
        $type = FleetCompliance::documentType($assetType);

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), fcmpUploadAllPayload($assetType, $type->id), FleetCompliance::json())
            ->assertStatus(422)->assertJson(['success' => false]);

        expect(Storage::disk('local')->allFiles())->toBe([]);
    })->with(['vehicle', 'trailer']);

    it('reports days until expiry as whole days', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        FleetCompliance::document($asset, FleetCompliance::documentType($assetType), ['expiry_date' => now()->addDays(10)->toDateString()]);

        $row = $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk()->viewData($assetType.'s')->items()[0];

        expect($row['document_details'][0]['days_until_expiry'])->toBe(10)
            ->and($row['expiring_documents'][0])->toContain('expires in 10 days');

        $this->actingAs($owner)
            ->getJson(route('admin.compliance.'.$assetType.'.details', $asset->id))
            ->assertOk()
            ->assertJsonPath($assetType.'.documents.0.days_until_expiry', 10);
    })->with(['vehicle', 'trailer']);

    it('counts trailers apart from vehicles in "Need Attention"', function () {
        $owner = Actors::companyOwner();
        FleetCompliance::documentType('trailer');
        FleetCompliance::assetOf($owner, 'vehicle');
        FleetCompliance::assetOf($owner, 'trailer');

        $response = $this->actingAs($owner)->get(route('admin.compliance.fleet'))->assertOk();

        expect($response->viewData('vehiclesNeedingAttention'))->toBe(0)
            ->and($response->viewData('trailersNeedingAttention'))->toBe(1)
            ->and($response->viewData('totalWarning'))->toBe(0)
            ->and($response->viewData('totalCritical'))->toBe(1);
        $response->assertSee('0 Vehicles Need Attention');
    });
});

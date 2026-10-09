<?php

use App\Models\DriverComplianceDocument;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\DriverCompliance\DriverCompliance;
use Tests\Support\Actors;

/*
| "Upload to all drivers" (decided 2026-10-10):
| - it covers only *active* drivers (not draft, pending, rejected, withdrawn or deleted ones);
| - a tenant always uploads to their own company; a super-admin uploads to one chosen company
|   (the company of the driver the modal was opened for), never to every company at once;
| - the form's checkbox posts value="1", so the `boolean` rule accepts it (it posted "on").
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

function uploadToAllPayload($type, array $overrides = []): array
{
    return array_merge([
        'document_type_id' => $type->id,
        'asset_type' => 'driver',
        'upload_to_all' => '1',
        'file' => DriverCompliance::pdf(),
    ], $overrides);
}

describe('Upload to all: which drivers', function () {
    it('uploads only to the tenant\'s active drivers', function () {
        [$owner, $active] = DriverCompliance::tenantWithDriver();
        foreach (['draft', 'pending', 'rejected', 'withdrawn'] as $status) {
            DriverCompliance::driverOf($owner, ['status' => $status]);
        }
        DriverCompliance::driverOf($owner)->delete();
        DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();

        $this->actingAs($owner)
            ->post(route('admin.compliance.driver.documents.upload'), uploadToAllPayload($type), DriverCompliance::json())
            ->assertOk()->assertJson(['uploaded_count' => 1]);

        expect(DriverComplianceDocument::pluck('driver_id')->all())->toBe([$active->id]);
    });

    it('ignores a company_id sent by a tenant', function () {
        [$owner, $mine] = DriverCompliance::tenantWithDriver();
        [$other] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();

        $this->actingAs($owner)
            ->post(
                route('admin.compliance.driver.documents.upload'),
                uploadToAllPayload($type, ['company_id' => Actors::companyOf($other)->id]),
                DriverCompliance::json()
            )
            ->assertOk()->assertJson(['uploaded_count' => 1]);

        expect(DriverComplianceDocument::pluck('driver_id')->all())->toBe([$mine->id]);
    });

    it('uploads only to the chosen company\'s active drivers for a super-admin', function () {
        [$chosen, $a] = DriverCompliance::tenantWithDriver();
        $b = DriverCompliance::driverOf($chosen);
        DriverCompliance::driverOf($chosen, ['status' => 'pending']);
        DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();

        $this->actingAs(Actors::superAdmin())
            ->post(
                route('admin.compliance.driver.documents.upload'),
                uploadToAllPayload($type, ['company_id' => Actors::companyOf($chosen)->id]),
                DriverCompliance::json()
            )
            ->assertOk()->assertJson(['uploaded_count' => 2]);

        expect(DriverComplianceDocument::pluck('driver_id')->sort()->values()->all())->toBe([$a->id, $b->id]);
    });

    it('requires a super-admin to choose a company, and stores nothing without one', function (array $overrides) {
        DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();

        $this->actingAs(Actors::superAdmin())
            ->post(route('admin.compliance.driver.documents.upload'), uploadToAllPayload($type, $overrides), DriverCompliance::json())
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        expect(DriverComplianceDocument::count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    })->with([
        'no company' => [[]],
        'unknown company' => [['company_id' => 999999]],
    ]);

    it('refuses, and stores nothing, when the company has no active driver', function () {
        [$owner] = DriverCompliance::tenantWithDriver(['status' => 'pending']);
        $type = DriverCompliance::documentType();

        $this->actingAs($owner)
            ->post(route('admin.compliance.driver.documents.upload'), uploadToAllPayload($type), DriverCompliance::json())
            ->assertStatus(422)
            ->assertJson(['message' => 'There are no active drivers to upload this document to.']);

        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('still uploads a single document to a driver who is not active', function () {
        [$owner, $pending] = DriverCompliance::tenantWithDriver(['status' => 'pending']);

        $this->actingAs($owner)
            ->post(route('admin.compliance.driver.documents.upload'), DriverCompliance::payload($pending, DriverCompliance::documentType()), DriverCompliance::json())
            ->assertOk()->assertJson(['uploaded_count' => 1]);
    });
});

describe('Upload to all: the modal', function () {
    it('posts a checkbox value the server accepts, and the company to upload to', function () {
        [$owner] = DriverCompliance::tenantWithDriver();

        $html = $this->actingAs($owner)->get(route('admin.compliance.drivers'))->assertOk()->getContent();

        expect($html)->toContain('name="upload_to_all" value="1"')
            ->and($html)->toContain('name="company_id"')
            ->and($html)->toContain('&driver_id=');
    });

    it('lists the tenant\'s own drivers and company', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();
        $company = Actors::companyOf($owner);

        $this->actingAs($owner)
            ->getJson(route('admin.compliance.drivers.list', ['document_type_id' => $type->id, 'driver_id' => $driver->id]))
            ->assertOk()
            ->assertJsonCount(1, 'assets')
            ->assertJsonPath('assets.0.id', $driver->id)
            ->assertJsonPath('company', ['id' => $company->id, 'name' => $company->company_name]);
    });

    it('lists, for a super-admin, only the company of the driver the modal was opened for', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $colleague = DriverCompliance::driverOf($owner);
        DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();
        $company = Actors::companyOf($owner);

        $response = $this->actingAs(Actors::superAdmin())
            ->getJson(route('admin.compliance.drivers.list', ['document_type_id' => $type->id, 'driver_id' => $driver->id]))
            ->assertOk()
            ->assertJsonPath('company.id', $company->id);

        expect(collect($response->json('assets'))->pluck('id')->sort()->values()->all())->toBe([$driver->id, $colleague->id]);
    });

    it('gives a super-admin no company, so no "upload to all", without a driver', function () {
        DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();

        $this->actingAs(Actors::superAdmin())
            ->getJson(route('admin.compliance.drivers.list', ['document_type_id' => $type->id]))
            ->assertOk()
            ->assertJsonPath('company', null);
    });
});

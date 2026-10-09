<?php

use App\Models\DriverComplianceDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\DriverCompliance\DriverCompliance;
use Tests\Support\Actors;

/*
| DCMP-06: the list, upload and delete endpoints returned `$e->getMessage()` in their JSON
| (SQL errors, model class names, file paths). Their catch-all also turned the tenant check's
| 403 into a 500. Failures are now logged and answered with a generic message; a missing
| record is 404 and another tenant's record is 403, as on the view/download routes.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

describe('DCMP-06: no exception text in responses', function () {
    it('logs a failed driver list and returns a generic message', function () {
        [$owner] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();
        Schema::drop('driver_compliance_documents');
        Log::spy();

        $response = $this->actingAs($owner)
            ->getJson(route('admin.compliance.drivers.list', ['document_type_id' => $type->id]))
            ->assertStatus(500)
            ->assertJson(['success' => false]);

        expect($response->json('message'))->toBe('Failed to load drivers.');
        Log::shouldHaveReceived('error')->once();
    });

    it('logs a failed upload, returns a generic message and removes the stored file', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();
        Schema::drop('driver_compliance_documents');
        Log::spy();

        $response = $this->actingAs($owner)
            ->post(route('admin.compliance.driver.documents.upload'), DriverCompliance::payload($driver, $type), DriverCompliance::json())
            ->assertStatus(500)
            ->assertJson(['success' => false]);

        expect($response->json('message'))->toBe('Failed to upload document.');
        Log::shouldHaveReceived('error')->once();
        expect(Storage::disk('local')->allFiles())->toBe([])
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('returns 404 for an unknown document on delete', function () {
        $owner = Actors::companyOwner();

        $response = $this->actingAs($owner)
            ->deleteJson(route('admin.compliance.driver.documents.delete'), ['document_id' => 999999])
            ->assertNotFound();

        expect((string) $response->getContent())->not->toContain('App\\\\Models');
    });

    it('returns 404 for an unknown driver on upload, and stores nothing', function () {
        $owner = Actors::companyOwner();
        $type = DriverCompliance::documentType();

        $this->actingAs($owner)
            ->post(route('admin.compliance.driver.documents.upload'), [
                'document_type_id' => $type->id,
                'asset_type' => 'driver',
                'selected_asset' => 999999,
                'file' => DriverCompliance::pdf(),
            ], DriverCompliance::json())
            ->assertNotFound();

        expect(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('returns 403 and keeps the document when another tenant deletes it', function () {
        [, $driver] = DriverCompliance::tenantWithDriver();
        $document = DriverCompliance::document($driver, DriverCompliance::documentType());

        $this->actingAs(Actors::companyOwner())
            ->deleteJson(route('admin.compliance.driver.documents.delete'), ['document_id' => $document->id])
            ->assertForbidden();

        expect($document->fresh())->not->toBeNull();
        Storage::disk('local')->assertExists($document->file_path);
    });

    it('returns 403 and stores nothing when uploading for another tenant\'s driver', function () {
        [, $driver] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();

        $this->actingAs(Actors::companyOwner())
            ->post(route('admin.compliance.driver.documents.upload'), DriverCompliance::payload($driver, $type), DriverCompliance::json())
            ->assertForbidden();

        expect(DriverComplianceDocument::count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    it('still uploads to "all drivers" only within the tenant', function () {
        [$owner, $mine] = DriverCompliance::tenantWithDriver();
        [, $theirs] = DriverCompliance::tenantWithDriver();
        $type = DriverCompliance::documentType();

        $this->actingAs($owner)
            ->post(route('admin.compliance.driver.documents.upload'), [
                'document_type_id' => $type->id,
                'asset_type' => 'driver',
                'upload_to_all' => '1',
                'file' => DriverCompliance::pdf(),
            ], DriverCompliance::json())
            ->assertOk()->assertJson(['uploaded_count' => 1]);

        expect(DriverComplianceDocument::pluck('driver_id')->all())->toBe([$mine->id]);
    });
});

describe('DCMP-07: a deleted driver\'s documents', function () {
    it('are not served or deleted for another tenant', function () {
        [, $driver] = DriverCompliance::tenantWithDriver();
        // On the legacy public disk, so the current code would find the file to serve.
        $document = DriverCompliance::document($driver, DriverCompliance::documentType(), disk: 'public');
        $driver->delete();
        $intruder = Actors::companyOwner();

        $this->actingAs($intruder)->get(route('admin.compliance.driver.documents.view', $document->id))->assertNotFound();
        $this->actingAs($intruder)->get(route('admin.compliance.driver.documents.download', $document->id))->assertNotFound();
        $this->actingAs($intruder)
            ->deleteJson(route('admin.compliance.driver.documents.delete'), ['document_id' => $document->id])
            ->assertNotFound();

        expect($document->fresh())->not->toBeNull();
        Storage::disk('public')->assertExists($document->file_path);
    });

    it('are not served to their own tenant either, like the rest of a deleted driver', function () {
        [$owner, $driver] = DriverCompliance::tenantWithDriver();
        $document = DriverCompliance::document($driver, DriverCompliance::documentType());
        $driver->delete();

        $this->actingAs($owner)->get(route('admin.compliance.driver.documents.view', $document->id))->assertNotFound();
    });
});

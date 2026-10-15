<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\FleetCompliance\FleetCompliance;
use Tests\Support\Actors;
use Tests\Support\Files;

/*
| FCMP-05: the list, upload and delete endpoints returned `$e->getMessage()` in their JSON
| (SQL errors, model class names, file paths). Their catch-all also turned the tenant check's
| 403 into a 500. Failures are now logged and answered with a generic message; a missing
| record is 404, and so is another tenant's record (WP5: foreign ids are 404).
|
| FCMP-07 (found 2026-10-10): view, download and delete ran the tenant check only
| `if ($document->vehicle)` / `if ($document->trailer)`. Vehicles and trailers are soft-deleted,
| so the parent loads as null and the check was skipped: any tenant could read or delete a
| deleted asset's documents by id.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

describe('FCMP-05: no exception text in responses', function () {
    it('logs a failed asset list and returns a generic message', function (string $assetType, string $route, string $message) {
        [$owner] = FleetCompliance::tenantWithAsset($assetType);
        $type = FleetCompliance::documentType($assetType);
        Schema::drop($assetType.'_documents');
        Log::spy();

        $response = $this->actingAs($owner)
            ->getJson(route($route, ['document_type_id' => $type->id]))
            ->assertStatus(500)
            ->assertJson(['success' => false]);

        expect($response->json('message'))->toBe($message);
        Log::shouldHaveReceived('error')->once();
    })->with([
        'vehicles' => ['vehicle', 'admin.compliance.vehicles.list', 'Failed to load vehicles.'],
        'trailers' => ['trailer', 'admin.compliance.trailers.list', 'Failed to load trailers.'],
    ]);

    it('logs a failed upload, returns a generic message and removes the stored file', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $type = FleetCompliance::documentType($assetType);
        Schema::drop($assetType.'_documents');
        Log::spy();

        $response = $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), FleetCompliance::payload($asset, $type), FleetCompliance::json())
            ->assertStatus(500)
            ->assertJson(['success' => false]);

        expect($response->json('message'))->toBe('Failed to upload document.');
        Log::shouldHaveReceived('error')->once();
        expect(Storage::disk('local')->allFiles())->toBe([])
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    })->with(['vehicle', 'trailer']);

    it('returns 404 for an unknown document on delete', function (string $assetType) {
        $response = $this->actingAs(Actors::companyOwner())
            ->deleteJson(route('admin.compliance.documents.delete'), ['document_id' => 999999, 'asset_type' => $assetType])
            ->assertNotFound();

        expect((string) $response->getContent())->not->toContain('App\\\\Models');
    })->with(['vehicle', 'trailer']);

    it('returns 404 for an unknown asset on upload, and stores nothing', function (string $assetType) {
        $owner = Actors::companyOwner();
        $type = FleetCompliance::documentType($assetType);

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), [
                'document_type_id' => $type->id,
                'asset_type' => $assetType,
                'selected_asset' => 999999,
                'expiry_date' => now()->addYear()->toDateString(),
                'file' => FleetCompliance::pdf(),
            ], FleetCompliance::json())
            ->assertNotFound();

        expect(Storage::disk('local')->allFiles())->toBe([])
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    })->with(['vehicle', 'trailer']);

    it('returns 404 and keeps the document when another tenant deletes it', function (string $assetType) {
        [, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $document = FleetCompliance::document($asset, FleetCompliance::documentType($assetType));

        $response = $this->actingAs(Actors::companyOwner())
            ->deleteJson(route('admin.compliance.documents.delete'), ['document_id' => $document->id, 'asset_type' => $assetType])
            ->assertNotFound();

        expect($document->fresh())->not->toBeNull()
            ->and((string) $response->getContent())->not->toContain('Failed to delete document:');
        Storage::disk('local')->assertExists(Files::pathOf($document->file_id));
    })->with(['vehicle', 'trailer']);

    it('returns 404 and stores nothing when uploading for another tenant\'s asset', function (string $assetType) {
        [, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $type = FleetCompliance::documentType($assetType);

        $this->actingAs(Actors::companyOwner())
            ->post(route('admin.compliance.documents.upload'), FleetCompliance::payload($asset, $type), FleetCompliance::json())
            ->assertNotFound();

        expect(FleetCompliance::documentModel($assetType)::withoutTenancy()->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([])
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    })->with(['vehicle', 'trailer']);

    it('still uploads to "all" only within the tenant', function (string $assetType) {
        [$owner, $mine] = FleetCompliance::tenantWithAsset($assetType);
        [, $theirs] = FleetCompliance::tenantWithAsset($assetType);
        $type = FleetCompliance::documentType($assetType);

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), [
                'document_type_id' => $type->id,
                'asset_type' => $assetType,
                'upload_to_all' => '1',
                'expiry_date' => now()->addYear()->toDateString(),
                'file' => FleetCompliance::pdf(),
            ], FleetCompliance::json())
            ->assertOk()->assertJson(['uploaded_count' => 1]);

        $model = FleetCompliance::documentModel($assetType);
        expect($model::withoutTenancy()->where($assetType.'_id', $mine->id)->exists())->toBeTrue()
            ->and($model::withoutTenancy()->where($assetType.'_id', $theirs->id)->exists())->toBeFalse();
    })->with(['vehicle', 'trailer']);
});

describe('FCMP-07: documents of a soft-deleted vehicle or trailer', function () {
    it('are not served or deleted for another tenant', function (string $assetType) {
        [, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $document = FleetCompliance::document($asset, FleetCompliance::documentType($assetType));
        $asset->delete();
        $intruder = Actors::companyOwner();

        $this->actingAs($intruder)->get(route('admin.compliance.documents.view', [$document->id, $assetType]))->assertNotFound();
        $this->actingAs($intruder)->get(route('admin.compliance.documents.download', [$document->id, $assetType]))->assertNotFound();
        $this->actingAs($intruder)
            ->deleteJson(route('admin.compliance.documents.delete'), ['document_id' => $document->id, 'asset_type' => $assetType])
            ->assertNotFound();

        expect($document->fresh())->not->toBeNull();
        Storage::disk('local')->assertExists(Files::pathOf($document->file_id));
    })->with(['vehicle', 'trailer']);

    it('are not served to their own tenant either, like the rest of a deleted asset', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $document = FleetCompliance::document($asset, FleetCompliance::documentType($assetType));
        $asset->delete();

        $this->actingAs($owner)->get(route('admin.compliance.documents.view', [$document->id, $assetType]))->assertNotFound();
    })->with(['vehicle', 'trailer']);
});

<?php

use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Modules\FleetCompliance\FleetCompliance;
use Tests\Support\Files;

/*
| FCMP-01: "upload to all" stores ONE file and points every vehicle's (or trailer's) document
| at it. Replacing or deleting one asset's document deleted that shared file, so every other
| asset's document lost its file. A file is now only deleted once no document uses it.
*/

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

/**
 * Upload one file to all three of the owner's assets and return its stored path.
 */
function fcmpUploadToAll($test, User $owner, DocumentType $type, string $assetType): string
{
    $test->actingAs($owner)
        ->post(route('admin.compliance.documents.upload'), [
            'document_type_id' => $type->id,
            'asset_type' => $assetType,
            'upload_to_all' => '1',
            'expiry_date' => now()->addYear()->toDateString(),
            'file' => FleetCompliance::pdf('policy.pdf'),
        ], FleetCompliance::json())
        ->assertOk()->assertJson(['success' => true, 'uploaded_count' => 3]);

    return Files::pathOf(FleetCompliance::documentModel($assetType)::withoutTenancy()->where('document_type_id', $type->id)->value('file_id'));
}

describe('FCMP-01: a file shared by "upload to all"', function () {
    it('stays for the other assets when one asset\'s document is replaced', function (string $assetType) {
        [$owner, $a] = FleetCompliance::tenantWithAsset($assetType);
        $b = FleetCompliance::assetOf($owner, $assetType);
        FleetCompliance::assetOf($owner, $assetType);
        $type = FleetCompliance::documentType($assetType);
        $shared = fcmpUploadToAll($this, $owner, $type, $assetType);

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), FleetCompliance::payload($a, $type), FleetCompliance::json())
            ->assertOk();

        $model = FleetCompliance::documentModel($assetType);
        expect(Storage::disk('local')->exists($shared) || Storage::disk('public')->exists($shared))->toBeTrue()
            ->and(Files::pathOf($model::withoutTenancy()->where($assetType.'_id', $b->id)->value('file_id')))->toBe($shared);
        $this->actingAs($owner)
            ->get(route('admin.compliance.documents.view', [$model::withoutTenancy()->where($assetType.'_id', $b->id)->value('id'), $assetType]))
            ->assertOk();
    })->with(['vehicle', 'trailer']);

    it('stays for the other assets when one asset\'s document is deleted', function (string $assetType) {
        [$owner, $a] = FleetCompliance::tenantWithAsset($assetType);
        FleetCompliance::assetOf($owner, $assetType);
        FleetCompliance::assetOf($owner, $assetType);
        $type = FleetCompliance::documentType($assetType);
        $shared = fcmpUploadToAll($this, $owner, $type, $assetType);
        $model = FleetCompliance::documentModel($assetType);

        $this->actingAs($owner)
            ->deleteJson(route('admin.compliance.documents.delete'), [
                'document_id' => $model::withoutTenancy()->where($assetType.'_id', $a->id)->value('id'),
                'asset_type' => $assetType,
            ])
            ->assertOk();

        expect(Storage::disk('local')->exists($shared) || Storage::disk('public')->exists($shared))->toBeTrue()
            ->and($model::withoutTenancy()->where('file_id', Files::idOf($shared))->count())->toBe(2);
    })->with(['vehicle', 'trailer']);

    it('is deleted once the last document using it is replaced', function (string $assetType) {
        [$owner, $a] = FleetCompliance::tenantWithAsset($assetType);
        $b = FleetCompliance::assetOf($owner, $assetType);
        $c = FleetCompliance::assetOf($owner, $assetType);
        $type = FleetCompliance::documentType($assetType);
        $shared = fcmpUploadToAll($this, $owner, $type, $assetType);

        foreach ([$a, $b, $c] as $asset) {
            $this->actingAs($owner)
                ->post(route('admin.compliance.documents.upload'), FleetCompliance::payload($asset, $type), FleetCompliance::json())
                ->assertOk();
        }

        Storage::disk('local')->assertMissing($shared);
        Storage::disk('public')->assertMissing($shared);
    })->with(['vehicle', 'trailer']);

    it('replaces every asset\'s shared file when "upload to all" runs again, and deletes the old one', function () {
        [$owner] = FleetCompliance::tenantWithAsset();
        FleetCompliance::assetOf($owner);
        FleetCompliance::assetOf($owner);
        $type = FleetCompliance::documentType();
        $first = fcmpUploadToAll($this, $owner, $type, 'vehicle');

        $this->actingAs($owner)
            ->post(route('admin.compliance.documents.upload'), [
                'document_type_id' => $type->id,
                'asset_type' => 'vehicle',
                'upload_to_all' => '1',
                'expiry_date' => now()->addYear()->toDateString(),
                'file' => FleetCompliance::pdf('policy-2.pdf'),
            ], FleetCompliance::json())
            ->assertOk()->assertJson(['updated_count' => 3]);

        $paths = FleetCompliance::documentModel('vehicle')::withoutTenancy()->pluck('file_id')->unique()->map(fn ($id) => Files::pathOf($id));
        expect($paths)->toHaveCount(1)->and($paths->first())->not->toBe($first);
        Storage::disk('local')->assertExists($paths->first());
        Storage::disk('local')->assertMissing($first);
    });

    // Documents are soft-deleted (01 §5): the row keeps its file so it can be restored
    it('keeps the file of a deleted document nobody else uses, for restore', function (string $assetType) {
        [$owner, $asset] = FleetCompliance::tenantWithAsset($assetType);
        $document = FleetCompliance::document($asset, FleetCompliance::documentType($assetType));

        $this->actingAs($owner)
            ->deleteJson(route('admin.compliance.documents.delete'), ['document_id' => $document->id, 'asset_type' => $assetType])
            ->assertOk();

        Storage::disk('local')->assertExists(Files::pathOf($document->file_id));
        expect(FleetCompliance::documentModel($assetType)::withoutTenancy()->onlyTrashed()->count())->toBe(1);
    })->with(['vehicle', 'trailer']);
});

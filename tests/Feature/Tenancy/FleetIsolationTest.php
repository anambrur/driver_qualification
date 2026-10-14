<?php

use App\Models\Trailer;
use App\Models\TrailerDocument;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Tenancy\TenantContext;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\StoredFileFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;

/*
| WP5 group 2: vehicles, trailers and their compliance documents carry the tenant scope
| (03 §4.2). Another company's asset or document is a 404 by URL and by request body.
*/

function fleetIsoDocument(Vehicle|Trailer $asset): VehicleDocument|TrailerDocument
{
    $type = $asset instanceof Vehicle ? 'vehicle' : 'trailer';
    $model = $type === 'vehicle' ? VehicleDocument::class : TrailerDocument::class;

    return app(TenantContext::class)->run($asset->company_id, fn () => $model::create([
        $type.'_id' => $asset->id,
        'document_type_id' => DocumentTypeFactory::new()->module($type)->create()->id,
        'file_id' => StoredFileFactory::new()->forCompany($asset->company)->create()->id,
        'expiry_date' => now()->addYear()->toDateString(),
    ]));
}

beforeEach(function () {
    Storage::fake('local');
    $this->companyA = Actors::company();
    $this->companyB = Actors::company();
    $this->assetsA = [
        'vehicle' => VehicleFactory::new()->forCompany($this->companyA)->create(),
        'trailer' => TrailerFactory::new()->forCompany($this->companyA)->create(),
    ];
    $this->assetsB = [
        'vehicle' => VehicleFactory::new()->forCompany($this->companyB)->create(),
        'trailer' => TrailerFactory::new()->forCompany($this->companyB)->create(),
    ];
    $this->ownerB = $this->companyB->owner;
});

describe('scope', function () {
    it('shows each company only its own assets and documents', function () {
        foreach ([...$this->assetsA, ...$this->assetsB] as $asset) {
            fleetIsoDocument($asset);
        }

        $seen = app(TenantContext::class)->run($this->companyB->id, fn () => [
            Vehicle::pluck('id')->all(),
            Trailer::pluck('id')->all(),
            VehicleDocument::pluck('vehicle_id')->all(),
            TrailerDocument::pluck('trailer_id')->all(),
        ]);

        expect($seen)->toBe([
            [$this->assetsB['vehicle']->id], [$this->assetsB['trailer']->id],
            [$this->assetsB['vehicle']->id], [$this->assetsB['trailer']->id],
        ]);
    });
});

describe('another company\'s asset by URL', function () {
    it('is a 404', function (string $assetType, string $method, string $route) {
        $this->actingAs($this->ownerB)
            ->json($method, route($route, $this->assetsA[$assetType]->id))
            ->assertNotFound();
    })->with([
        'vehicle edit' => ['vehicle', 'GET', 'admin.vehicle.edit'],
        'vehicle update' => ['vehicle', 'PUT', 'admin.vehicle.update'],
        'vehicle destroy' => ['vehicle', 'DELETE', 'admin.vehicle.destroy'],
        'vehicle restore' => ['vehicle', 'POST', 'admin.vehicle.restore'],
        'vehicle details' => ['vehicle', 'GET', 'admin.vehicles.get-vehicle-details'],
        'vehicle compliance details' => ['vehicle', 'GET', 'admin.compliance.vehicle.details'],
        'trailer edit' => ['trailer', 'GET', 'admin.trailer.edit'],
        'trailer update' => ['trailer', 'PUT', 'admin.trailer.update'],
        'trailer destroy' => ['trailer', 'DELETE', 'admin.trailer.destroy'],
        'trailer restore' => ['trailer', 'POST', 'admin.trailer.restore'],
        'trailer details' => ['trailer', 'GET', 'admin.trailers.get-trailer-details'],
        'trailer compliance details' => ['trailer', 'GET', 'admin.compliance.trailer.details'],
    ]);

    it('keeps the asset when the delete is refused', function (string $assetType) {
        $asset = $this->assetsA[$assetType];

        $this->actingAs($this->ownerB)->deleteJson(route("admin.{$assetType}.destroy", $asset->id))->assertNotFound();

        expect($asset::withoutTenancy()->find($asset->id))->not->toBeNull();
    })->with(['vehicle', 'trailer']);
});

describe('another company\'s asset in the request body', function () {
    it('stores no compliance document for it', function (string $assetType) {
        $this->actingAs($this->ownerB)
            ->postJson(route('admin.compliance.documents.upload'), [
                'document_type_id' => DocumentTypeFactory::new()->module($assetType)->create()->id,
                'asset_type' => $assetType,
                'selected_asset' => $this->assetsA[$assetType]->id,
                'expiry_date' => now()->addYear()->toDateString(),
                'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            ])
            ->assertNotFound();

        expect(VehicleDocument::withoutTenancy()->count() + TrailerDocument::withoutTenancy()->count())->toBe(0)
            ->and(Storage::disk('local')->allFiles())->toBe([]);
    })->with(['vehicle', 'trailer']);

    it('sends no compliance reminder for it', function (string $assetType) {
        Mail::fake();

        $this->actingAs($this->ownerB)
            ->postJson(route('admin.compliance.documents.send-reminder'), [
                'asset_type' => $assetType,
                'asset_id' => $this->assetsA[$assetType]->id,
                'document_type_id' => DocumentTypeFactory::new()->module($assetType)->create()->id,
            ])
            ->assertNotFound();

        Mail::assertNothingOutgoing();
    })->with(['vehicle', 'trailer']);
});

describe('another company\'s compliance document', function () {
    it('is a 404 to view, download and delete', function (string $assetType) {
        $document = fleetIsoDocument($this->assetsA[$assetType]);

        $this->actingAs($this->ownerB)->get(route('admin.compliance.documents.view', [$document->id, $assetType]))->assertNotFound();
        $this->actingAs($this->ownerB)->get(route('admin.compliance.documents.download', [$document->id, $assetType]))->assertNotFound();
        $this->actingAs($this->ownerB)
            ->deleteJson(route('admin.compliance.documents.delete'), ['document_id' => $document->id, 'asset_type' => $assetType])
            ->assertNotFound();

        expect($document::withoutTenancy()->find($document->id))->not->toBeNull();
    })->with(['vehicle', 'trailer']);
});

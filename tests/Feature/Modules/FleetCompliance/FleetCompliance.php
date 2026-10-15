<?php

namespace Tests\Feature\Modules\FleetCompliance;

use App\Models\DocumentType;
use App\Models\Trailer;
use App\Models\TrailerDocument;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Tenancy\TenantContext;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\TrailerFactory;
use Database\Factories\VehicleFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;
use Tests\Support\Files;

/**
 * Fixtures for the fleet compliance dashboard and its vehicle/trailer document
 * upload/view/download/delete. Most helpers take an asset type ('vehicle' or 'trailer').
 */
final class FleetCompliance
{
    /**
     * A subscribed tenant and one asset of the given type in their company.
     *
     * @return array{0: User, 1: Vehicle|Trailer}
     */
    public static function tenantWithAsset(string $assetType = 'vehicle', array $attributes = []): array
    {
        $owner = Actors::companyOwner();

        return [$owner, self::assetOf($owner, $assetType, $attributes)];
    }

    public static function assetOf(User $owner, string $assetType = 'vehicle', array $attributes = []): Vehicle|Trailer
    {
        $factory = $assetType === 'vehicle' ? VehicleFactory::new() : TrailerFactory::new();

        return $factory->forCompany(Actors::companyOf($owner))->create($attributes);
    }

    public static function documentType(string $assetType = 'vehicle', array $attributes = []): DocumentType
    {
        return DocumentTypeFactory::new()->module($assetType)->create($attributes);
    }

    /**
     * A real file with PDF bytes, uploaded under any client name.
     */
    public static function pdf(string $clientName = 'insurance.pdf'): UploadedFile
    {
        return self::fileWith($clientName, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n", 'application/pdf');
    }

    /**
     * A real file with the given bytes, uploaded under any client name.
     */
    public static function fileWith(string $clientName, string $bytes, string $clientMime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $clientName, $clientMime, null, true);
    }

    /**
     * Upload payload for one asset.
     *
     * @return array<string, mixed>
     */
    public static function payload(Vehicle|Trailer $asset, DocumentType $type, array $overrides = []): array
    {
        return array_merge([
            'document_type_id' => $type->id,
            'asset_type' => self::typeOf($asset),
            'selected_asset' => $asset->id,
            'expiry_date' => now()->addYear()->toDateString(),
            'description' => 'Annual inspection',
            'file' => self::pdf(),
        ], $overrides);
    }

    /**
     * A stored document with its stored file on $disk.
     */
    public static function document(Vehicle|Trailer $asset, DocumentType $type, array $attributes = [], string $disk = 'local'): VehicleDocument|TrailerDocument
    {
        $assetType = self::typeOf($asset);
        $model = $assetType === 'vehicle' ? VehicleDocument::class : TrailerDocument::class;

        $file = Files::stored($asset->company_id, '%PDF-1.4', 'pdf', $disk);
        $document = app(TenantContext::class)->run($asset->company_id, fn () => $model::create(array_merge([
            $assetType.'_id' => $asset->id,
            'document_type_id' => $type->id,
            'expiry_date' => now()->addYear()->toDateString(),
            'file_id' => $file->id,
        ], $attributes)));

        Storage::disk($disk)->put($file->path, "%PDF-1.4\nbytes-of-{$document->id}");

        return $document;
    }

    /**
     * @return class-string<VehicleDocument>|class-string<TrailerDocument>
     */
    public static function documentModel(string $assetType): string
    {
        return $assetType === 'vehicle' ? VehicleDocument::class : TrailerDocument::class;
    }

    public static function typeOf(Vehicle|Trailer $asset): string
    {
        return $asset instanceof Vehicle ? 'vehicle' : 'trailer';
    }

    /**
     * @return array<string, string>
     */
    public static function json(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }
}

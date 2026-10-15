<?php

namespace Tests\Feature\Modules\DriverCompliance;

use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\DriverComplianceDocument;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Actors;
use Tests\Support\Files;

/**
 * Fixtures for the driver compliance dashboard and its document upload/view/download/delete.
 */
final class DriverCompliance
{
    /**
     * A subscribed tenant and an active driver in their company.
     *
     * @return array{0: User, 1: Driver}
     */
    public static function tenantWithDriver(array $driverAttributes = []): array
    {
        $owner = Actors::companyOwner();

        return [$owner, self::driverOf($owner, $driverAttributes)];
    }

    /**
     * A 'status' attribute is the display status (DriverFactory::status()). Drivers have a
     * current licence, so the CDL requirement (F17) is met and the document types decide.
     */
    public static function driverOf(User $owner, array $attributes = []): Driver
    {
        $factory = DriverFactory::new()->forCompany(Actors::companyOf($owner))->withCurrentLicense();

        if (isset($attributes['status'])) {
            $factory = $factory->status(Arr::pull($attributes, 'status'));
        }

        return $factory->create($attributes);
    }

    public static function documentType(array $attributes = []): DocumentType
    {
        return DocumentTypeFactory::new()->module('driver')->create($attributes);
    }

    /**
     * A real file with PDF bytes, uploaded under any client name.
     */
    public static function pdf(string $clientName = 'medical.pdf', string $extraBytes = ''): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n".$extraBytes."\n%%EOF\n");

        return new UploadedFile($path, $clientName, 'application/pdf', null, true);
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
     * Upload payload for one driver (the form posts asset_type=driver).
     *
     * @return array<string, mixed>
     */
    public static function payload(Driver $driver, DocumentType $type, array $overrides = []): array
    {
        return array_merge([
            'document_type_id' => $type->id,
            'asset_type' => 'driver',
            'selected_asset' => $driver->id,
            'file_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'description' => 'Annual review',
            'file' => self::pdf(),
        ], $overrides);
    }

    /**
     * A stored compliance document with its stored file on $disk.
     */
    public static function document(Driver $driver, DocumentType $type, array $attributes = [], string $disk = 'local'): DriverComplianceDocument
    {
        $file = Files::stored($driver->company_id, '%PDF-1.4', 'pdf', $disk);
        $document = app(TenantContext::class)->run($driver->company_id, fn () => DriverComplianceDocument::create(array_merge([
            'driver_id' => $driver->id,
            'document_type_id' => $type->id,
            'file_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'file_id' => $file->id,
        ], $attributes)));

        Storage::disk($disk)->put($file->path, "%PDF-1.4\nbytes-of-{$document->id}");

        return $document;
    }

    /**
     * @return array<string, string>
     */
    public static function json(): array
    {
        return ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'];
    }
}

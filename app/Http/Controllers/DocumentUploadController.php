<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DocumentType;
use App\Models\Trailer;
use App\Models\TrailerDocument;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DocumentUploadController extends Controller
{
    use CompanyFilterTrait;

    /**
     * FCMP-02: vehicle and trailer documents live on the private `local` disk under a random name
     * and are only served by viewDocument()/downloadDocument(). Files uploaded before that are still
     * on the `public` disk until the 2026_10_10_000100 migration moves them, so reads and deletes
     * check both.
     */
    private const DISK = 'local';

    private const LEGACY_DISK = 'public';

    private const FILE_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'private, no-store',
    ];

    private const ASSET_TYPES = ['vehicle', 'trailer'];

    /**
     * Get list of vehicles for upload dropdown (filtered by company)
     */
    public function getVehiclesList(Request $request)
    {
        try {
            $documentTypeId = $request->get('document_type_id');

            $documentType = DocumentType::find($documentTypeId);

            // The company "upload to all" applies to; a super-admin works on the company of the
            // vehicle the modal was opened for, and only sees that company's vehicles (PO-02).
            $company = $this->uploadCompany(Vehicle::class, $request->integer('asset_id') ?: null);

            // Apply company filter and load has_document in one query (avoid N+1 exists)
            $vehiclesQuery = Vehicle::select('vehicles.id', 'vehicles.unit_no')
                ->withExists([
                    'documents as has_document' => function ($query) use ($documentTypeId) {
                        $query->where('document_type_id', $documentTypeId);
                    },
                ]);
            $vehiclesQuery = $this->applyCompanyFilter($vehiclesQuery);
            if ($this->isSuperAdmin() && $company) {
                $vehiclesQuery->where('vehicles.company_id', $company->id);
            }

            $vehicles = $vehiclesQuery->orderBy('vehicles.unit_no')
                ->get()
                ->map(function ($vehicle) {
                    return [
                        'id' => $vehicle->id,
                        'unit_no' => $vehicle->unit_no,
                        'has_document' => (bool) $vehicle->getAttribute('has_document'),
                    ];
                });

            // Check if user has any vehicles
            if ($vehicles->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'assets' => [],
                    'document_type_name' => $documentType ? $documentType->name : 'Document',
                    'company' => $this->companyPayload($company),
                    'message' => 'No vehicles found for your company',
                ]);
            }

            return response()->json([
                'success' => true,
                'assets' => $vehicles,
                'document_type_name' => $documentType ? $documentType->name : 'Document',
                'company' => $this->companyPayload($company),
                'total' => $vehicles->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load vehicles for compliance upload', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load vehicles.',
            ], 500);
        }
    }

    /**
     * Get list of trailers for upload dropdown (filtered by company)
     */
    public function getTrailersList(Request $request)
    {
        try {
            $documentTypeId = $request->get('document_type_id');

            $documentType = DocumentType::find($documentTypeId);

            // The company "upload to all" applies to; a super-admin works on the company of the
            // trailer the modal was opened for, and only sees that company's trailers (PO-02).
            $company = $this->uploadCompany(Trailer::class, $request->integer('asset_id') ?: null);

            // Apply company filter and load has_document in one query (avoid N+1 exists)
            $trailersQuery = Trailer::select('trailers.id', 'trailers.unit_no')
                ->withExists([
                    'documents as has_document' => function ($query) use ($documentTypeId) {
                        $query->where('document_type_id', $documentTypeId);
                    },
                ]);
            $trailersQuery = $this->applyCompanyFilter($trailersQuery);
            if ($this->isSuperAdmin() && $company) {
                $trailersQuery->where('trailers.company_id', $company->id);
            }

            $trailers = $trailersQuery->orderBy('trailers.unit_no')
                ->get()
                ->map(function ($trailer) {
                    return [
                        'id' => $trailer->id,
                        'unit_no' => $trailer->unit_no,
                        'has_document' => (bool) $trailer->getAttribute('has_document'),
                    ];
                });

            // Check if user has any trailers
            if ($trailers->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'assets' => [],
                    'document_type_name' => $documentType ? $documentType->name : 'Document',
                    'company' => $this->companyPayload($company),
                    'message' => 'No trailers found for your company',
                ]);
            }

            return response()->json([
                'success' => true,
                'assets' => $trailers,
                'document_type_name' => $documentType ? $documentType->name : 'Document',
                'company' => $this->companyPayload($company),
                'total' => $trailers->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load trailers for compliance upload', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load trailers.',
            ], 500);
        }
    }

    /**
     * Upload document(s) with company validation
     */
    public function uploadDocument(Request $request)
    {
        $isSuperAdmin = $this->isSuperAdmin();
        $uploadToAll = $request->boolean('upload_to_all');

        // Validate request
        $validator = Validator::make($request->all(), [
            // Only a fleet document type of this asset type can be attached (PO-22)
            'document_type_id' => ['required', Rule::exists('document_types', 'id')->where('module', $request->input('asset_type'))],
            'asset_type' => 'required|in:vehicle,trailer',
            'expiry_date' => 'required|date|after_or_equal:today',
            'description' => 'nullable|string|max:500',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:20480', // 20MB max
            // A browser checkbox posts "on" (PO-02)
            'upload_to_all' => 'nullable|in:0,1,on,off,true,false',
            'selected_asset' => Rule::requiredIf(! $uploadToAll),
            // Tenants always upload to their own company; a super-admin names the company
            'company_id' => $isSuperAdmin
                ? [Rule::requiredIf($uploadToAll), 'nullable', 'integer', Rule::exists('companies', 'id')]
                : ['nullable'],
        ], [
            'document_type_id.exists' => 'The selected document type is invalid.',
            'company_id.required' => 'Choose the company whose assets should get this document.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $assetType = $request->asset_type;
        $assetModel = $assetType === 'vehicle' ? Vehicle::class : Trailer::class;

        if ($uploadToAll) {
            // Upload to all live assets of one company: the tenant's own, or the one a super-admin
            // chose (PO-02, same rule as drivers). Soft-deleted assets are excluded.
            $companyId = $isSuperAdmin ? $request->integer('company_id') : $this->getUserCompanyId();
            $assets = $assetModel::where('company_id', $companyId ?? -1)->get();

            if ($assets->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "There are no {$assetType}s to upload this document to.",
                ], 422);
            }
        } else {
            // Upload to selected asset (validate company access first): 404 if unknown, 403 if another company's
            $asset = $assetModel::whereKey($request->selected_asset)->first();
            abort_unless($asset !== null, 404, ucfirst($assetType).' not found.');
            $this->authorizeCompanyAccess($asset, "You do not have permission to upload documents for this {$assetType}.");
            $assets = collect([$asset]);
        }

        $filePath = null;
        $replacedPaths = [];
        $uploadedCount = 0;
        $updatedCount = 0;

        try {
            DB::beginTransaction();

            // Random name with the extension of the content (FCMP-02). One file is shared by
            // every asset of an "upload to all" (FCMP-01: see deleteFileIfUnused()).
            $filePath = $request->file('file')->store('documents/'.$assetType.'s', self::DISK);
            if (! $filePath) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }

            foreach ($assets as $asset) {
                $result = $this->createOrUpdateDocumentWithValidation(
                    $assetType,
                    $asset,
                    $request->document_type_id,
                    $request->expiry_date,
                    $request->description,
                    $filePath
                );

                if ($result['created']) {
                    $uploadedCount++;
                } else {
                    $updatedCount++;
                }

                if ($result['replaced_path']) {
                    $replacedPaths[] = $result['replaced_path'];
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded file if transaction fails
            if ($filePath) {
                Storage::disk(self::DISK)->delete($filePath);
            }

            Log::error('Failed to upload fleet compliance document', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document.',
            ], 500);
        }

        // Old files are only removed once the new rows are committed, and only if no other
        // asset's document still points at them.
        foreach (array_unique($replacedPaths) as $path) {
            $this->deleteFileIfUnused($assetType, $path);
        }

        $message = $this->generateSuccessMessage($uploadedCount, $updatedCount, $assetType);

        return response()->json([
            'success' => true,
            'message' => $message,
            'uploaded_count' => $uploadedCount,
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * Create or update document for an asset with validation
     *
     * @return array{created: bool, replaced_path: ?string}
     */
    private function createOrUpdateDocumentWithValidation(string $assetType, Vehicle|Trailer $asset, $documentTypeId, $expiryDate, $description, string $filePath): array
    {
        $created = false;
        $replacedPath = null;

        // Double-check the asset belongs to user's company
        if (! $this->userHasAccess($asset)) {
            throw new \Exception("Unauthorized access to {$assetType}");
        }

        $documentModel = $this->documentModel($assetType);

        $existingDocument = $documentModel::where($assetType.'_id', $asset->id)
            ->where('document_type_id', $documentTypeId)
            ->first();

        if ($existingDocument) {
            // The old file is deleted by the caller after commit, if no other document uses it
            if ($existingDocument->file_path && $existingDocument->file_path !== $filePath) {
                $replacedPath = $existingDocument->file_path;
            }

            // Update existing document
            $existingDocument->update([
                'expiry_date' => $expiryDate,
                'description' => $description,
                'file_path' => $filePath,
            ]);
        } else {
            // Create new document
            $documentModel::create([
                $assetType.'_id' => $asset->id,
                'document_type_id' => $documentTypeId,
                'expiry_date' => $expiryDate,
                'description' => $description,
                'file_path' => $filePath,
            ]);
            $created = true;
        }

        return ['created' => $created, 'replaced_path' => $replacedPath];
    }

    /**
     * Delete document with company validation
     */
    public function deleteDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_id' => 'required|integer',
            'asset_type' => 'required|in:vehicle,trailer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $assetType = $request->asset_type;
        $document = $this->findAuthorizedDocument($assetType, $request->document_id, "You do not have permission to delete documents for this {$assetType}.");

        try {
            $filePath = $document->file_path;

            // Delete document record
            $document->delete();

            // Delete file from storage, unless another asset's document shares it (FCMP-01)
            if ($filePath) {
                $this->deleteFileIfUnused($assetType, $filePath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete fleet compliance document', ['exception' => $e, 'document_id' => $document->id, 'asset_type' => $assetType]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document.',
            ], 500);
        }
    }

    /**
     * Download document with company validation
     */
    public function downloadDocument(Request $request, $documentId, $assetType)
    {
        $document = $this->findAuthorizedDocument($assetType, $documentId, "You do not have permission to download documents for this {$assetType}.");
        $disk = $this->diskHolding($document->file_path);

        $fileName = $document->documentType->name.'_'.basename($document->file_path);

        return Storage::disk($disk)->download($document->file_path, $fileName, self::FILE_HEADERS);
    }

    /**
     * View document with company validation
     */
    public function viewDocument($documentId, $assetType)
    {
        $document = $this->findAuthorizedDocument($assetType, $documentId, "You do not have permission to view documents for this {$assetType}.");
        $disk = $this->diskHolding($document->file_path);

        return Storage::disk($disk)->response($document->file_path, null, self::FILE_HEADERS);
    }

    private function isSuperAdmin(): bool
    {
        $this->resolveCompanyContext();

        return (bool) $this->resolvedSuperAdmin;
    }

    /**
     * The company the upload modal works on: a tenant's own company, or for a super-admin the
     * company of the given vehicle/trailer (null when there is none).
     *
     * @param  class-string<Vehicle>|class-string<Trailer>  $assetModel
     */
    private function uploadCompany(string $assetModel, ?int $assetId): ?Company
    {
        if (! $this->isSuperAdmin()) {
            return Company::find($this->getUserCompanyId());
        }

        return $assetId ? Company::find($assetModel::whereKey($assetId)->value('company_id')) : null;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function companyPayload(?Company $company): ?array
    {
        return $company ? ['id' => $company->id, 'name' => (string) $company->company_name] : null;
    }

    /**
     * @return class-string<VehicleDocument>|class-string<TrailerDocument>
     */
    private function documentModel(string $assetType): string
    {
        return $assetType === 'vehicle' ? VehicleDocument::class : TrailerDocument::class;
    }

    /**
     * Load a document the user may act on: 404 for an unknown asset type (FCMP-04), or if the
     * document or its vehicle/trailer is gone (a soft-deleted asset loads as null, FCMP-07),
     * 403 if the asset belongs to another company.
     */
    private function findAuthorizedDocument($assetType, $documentId, string $message): VehicleDocument|TrailerDocument
    {
        abort_unless(in_array($assetType, self::ASSET_TYPES, true), 404, 'Document not found');

        $document = $this->documentModel($assetType)::with(['documentType', $assetType])->whereKey($documentId)->first();
        $asset = $document?->getRelationValue($assetType);
        abort_unless($asset !== null, 404, 'Document not found');

        $this->authorizeCompanyAccess($asset, $message);

        return $document;
    }

    /**
     * The disk the file is on: the private disk, or the public one for files not yet moved.
     */
    private function diskHolding(?string $filePath): string
    {
        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            if ($filePath && Storage::disk($disk)->exists($filePath)) {
                return $disk;
            }
        }

        abort(404, 'File not found');
    }

    /**
     * Delete a file from both disks once no document points at it any more. "Upload to all"
     * gives every vehicle (or trailer) the same file (FCMP-01).
     */
    private function deleteFileIfUnused(string $assetType, string $filePath): void
    {
        if ($this->documentModel($assetType)::where('file_path', $filePath)->exists()) {
            return;
        }

        foreach ([self::DISK, self::LEGACY_DISK] as $disk) {
            Storage::disk($disk)->delete($filePath);
        }
    }

    /**
     * Generate success message
     */
    private function generateSuccessMessage($uploadedCount, $updatedCount, $assetType)
    {
        $assetLabel = $assetType === 'vehicle' ? 'vehicle' : 'trailer';
        $assetLabelPlural = $assetType === 'vehicle' ? 'vehicles' : 'trailers';

        $messages = [];

        if ($uploadedCount > 0) {
            $messages[] = $uploadedCount === 1
                ? "Document uploaded to 1 {$assetLabel}"
                : "Documents uploaded to {$uploadedCount} {$assetLabelPlural}";
        }

        if ($updatedCount > 0) {
            $messages[] = $updatedCount === 1
                ? "1 {$assetLabel} document updated"
                : "{$updatedCount} {$assetLabelPlural} documents updated";
        }

        return implode(' and ', $messages);
    }
}

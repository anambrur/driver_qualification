<?php

namespace App\Http\Controllers;

use App\Exceptions\LimitReached;
use App\Models\Company;
use App\Models\DocumentType;
use App\Models\Trailer;
use App\Models\TrailerDocument;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Compliance\ComplianceDocumentUploader;
use App\Services\Files\StoredFileService;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class DocumentUploadController extends Controller
{
    use CompanyFilterTrait;

    private const ASSET_TYPES = ['vehicle', 'trailer'];

    /**
     * Get list of vehicles for upload dropdown (filtered by company)
     */
    public function getVehiclesList(Request $request)
    {
        try {
            $documentTypeId = $request->get('document_type_id');

            $documentType = DocumentType::find($documentTypeId);

            // The company "upload to all" applies to: always the tenant's own (PO-02)
            $company = $this->uploadCompany();

            // Load has_document in one query (avoid N+1 exists); the tenant scope limits the rows
            $vehiclesQuery = Vehicle::select('vehicles.id', 'vehicles.unit_no')
                ->withExists([
                    'documents as has_document' => function ($query) use ($documentTypeId) {
                        $query->where('document_type_id', $documentTypeId);
                    },
                ]);

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

            // The company "upload to all" applies to: always the tenant's own (PO-02)
            $company = $this->uploadCompany();

            // Load has_document in one query (avoid N+1 exists); the tenant scope limits the rows
            $trailersQuery = Trailer::select('trailers.id', 'trailers.unit_no')
                ->withExists([
                    'documents as has_document' => function ($query) use ($documentTypeId) {
                        $query->where('document_type_id', $documentTypeId);
                    },
                ]);

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
    public function uploadDocument(Request $request, ComplianceDocumentUploader $uploader)
    {
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
        ], [
            'document_type_id.exists' => 'The selected document type is invalid.',
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
            // Upload to all live assets of the tenant's own company (the tenant scope); the
            // platform does this inside a read_write support session (PO-02, D11). Soft-deleted
            // assets are excluded.
            $assetIds = $assetModel::query()->pluck('id')->all();

            if ($assetIds === []) {
                return response()->json([
                    'success' => false,
                    'message' => "There are no {$assetType}s to upload this document to.",
                ], 422);
            }
        } else {
            // Upload to selected asset: 404 if unknown or another company's
            $asset = $assetModel::whereKey($request->selected_asset)->first();
            abort_unless($asset !== null, 404, ucfirst($assetType).' not found.');
            Gate::authorize('update', $asset);
            $assetIds = [$asset->id];
        }

        try {
            // One stored file is shared by every asset of an "upload to all" (FCMP-01)
            $result = $uploader->upload($this->documentModel($assetType), $assetType.'_id', $assetIds, (int) $request->document_type_id, [
                'expiry_date' => $request->expiry_date,
                'description' => $request->description,
            ], $request->file('file'), $assetType.'-documents');
        } catch (LimitReached $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to upload fleet compliance document', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $this->generateSuccessMessage($result['created'], $result['updated'], $assetType),
            'uploaded_count' => $result['created'],
            'updated_count' => $result['updated'],
        ]);
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
        $document = $this->findAuthorizedDocument($assetType, $request->document_id, 'update');

        try {
            // Soft delete: the row keeps its file so it can be restored
            $document->delete();

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
    public function downloadDocument(Request $request, $documentId, $assetType, StoredFileService $files)
    {
        $document = $this->findAuthorizedDocument($assetType, $documentId, 'view');

        return $files->response($document->file, $document->documentType->name.'_'.basename((string) $document->file?->path));
    }

    /**
     * View document with company validation
     */
    public function viewDocument($documentId, $assetType, StoredFileService $files)
    {
        $document = $this->findAuthorizedDocument($assetType, $documentId, 'view');

        return $files->response($document->file);
    }

    /**
     * The company the upload modal works on: the tenant's own.
     */
    private function uploadCompany(): ?Company
    {
        return Company::find($this->getUserCompanyId());
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
     * document or its vehicle/trailer is gone (a soft-deleted asset loads as null, FCMP-07) or
     * belongs to another company.
     */
    private function findAuthorizedDocument($assetType, $documentId, string $ability): VehicleDocument|TrailerDocument
    {
        abort_unless(in_array($assetType, self::ASSET_TYPES, true), 404, 'Document not found');

        $document = $this->documentModel($assetType)::with(['documentType', $assetType, 'file'])->whereKey($documentId)->first();
        $asset = $document?->getRelationValue($assetType);
        abort_unless($asset !== null, 404, 'Document not found');

        Gate::authorize($ability, $asset);

        return $document;
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

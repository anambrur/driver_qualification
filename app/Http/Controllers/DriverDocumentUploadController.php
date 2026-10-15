<?php

namespace App\Http\Controllers;

use App\Exceptions\LimitReached;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DocumentType;
use App\Models\Driver;
use App\Models\DriverComplianceDocument;
use App\Services\Compliance\ComplianceDocumentUploader;
use App\Services\Files\StoredFileService;
use App\Traits\CompanyFilterTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DriverDocumentUploadController extends Controller
{
    use CompanyFilterTrait;

    /**
     * Get list of drivers for upload dropdown (filtered by company)
     */
    public function getDriversList(Request $request)
    {
        try {
            $documentTypeId = $request->get('document_type_id');

            $documentType = DocumentType::find($documentTypeId);

            // The company "upload to all" applies to: always the tenant's own
            $company = $this->uploadCompany();

            // Load has_document in one query (avoid N+1 exists); the tenant scope limits the rows
            $driversQuery = Driver::select('drivers.id', 'drivers.first_name', 'drivers.last_name')
                ->withExists([
                    'documents as has_document' => function ($query) use ($documentTypeId) {
                        $query->where('document_type_id', $documentTypeId);
                    },
                ]);

            $drivers = $driversQuery->orderBy('drivers.first_name')
                ->orderBy('drivers.last_name')
                ->get()
                ->map(function ($driver) {
                    return [
                        'id' => $driver->id,
                        'full_name' => $driver->first_name.' '.$driver->last_name,
                        'has_document' => (bool) $driver->has_document,
                    ];
                });

            // Check if user has any drivers
            if ($drivers->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'assets' => [],
                    'document_type_name' => $documentType ? $documentType->name : 'Document',
                    'company' => $this->companyPayload($company),
                    'message' => 'No drivers found for your company',
                ]);
            }

            return response()->json([
                'success' => true,
                'assets' => $drivers,
                'document_type_name' => $documentType ? $documentType->name : 'Document',
                'company' => $this->companyPayload($company),
                'total' => $drivers->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load drivers for compliance upload', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load drivers.',
            ], 500);
        }
    }

    /**
     * Upload document(s) with company validation
     */
    public function uploadDocument(Request $request, ComplianceDocumentUploader $uploader)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'document_type_id' => 'required|exists:document_types,id',
            'asset_type' => 'required|in:driver',
            'file_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            'description' => 'nullable|string|max:500',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:20480', // 20MB max
            'upload_to_all' => 'nullable|boolean',
            'selected_asset' => 'required_without:upload_to_all',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if ($request->boolean('upload_to_all')) {
            // Upload to all active drivers of the tenant's own company (the tenant scope); the
            // platform does this inside a read_write support session (PO-02, D11)
            $driverIds = Driver::query()->where('employment_status', 'active')->pluck('id')->all();

            if ($driverIds === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'There are no active drivers to upload this document to.',
                ], 422);
            }
        } else {
            // Upload to selected driver (validate company access first)
            $driver = Driver::whereKey($request->selected_asset)->first();
            abort_unless($driver instanceof Driver, 404, 'Driver not found.');
            Gate::authorize('update', $driver);
            $driverIds = [$driver->id];
        }

        try {
            // One stored file is shared by every driver of an "upload to all" (DCMP-02)
            $result = $uploader->upload(DriverComplianceDocument::class, 'driver_id', $driverIds, (int) $request->document_type_id, [
                'file_date' => $request->file_date,
                'expiry_date' => $request->expiry_date,
                'description' => $request->description,
            ], $request->file('file'), 'driver-documents');
        } catch (LimitReached $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to upload driver compliance document', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $this->generateSuccessMessage($result['created'], $result['updated']),
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $document = $this->findAuthorizedDocument($request->document_id, 'update');

        try {
            // Soft delete: the row keeps its file so it can be restored
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => 'Document deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete driver compliance document', ['exception' => $e, 'document_id' => $document->id]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete document.',
            ], 500);
        }
    }

    /**
     * Download document with company validation
     */
    public function downloadDocument(Request $request, $documentId, StoredFileService $files)
    {
        $document = $this->findAuthorizedDocument($documentId, 'view');
        $response = $files->response($document->file, $document->documentType->name.'_'.basename((string) $document->file?->path));
        AuditLog::record($request->user(), $document->company_id, 'document.downloaded', $document, ['document_type' => $document->documentType->name]);

        return $response;
    }

    /**
     * View document with company validation
     */
    public function viewDocument($documentId, StoredFileService $files)
    {
        $document = $this->findAuthorizedDocument($documentId, 'view');

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
     * Load a document the user may act on: 404 if it or its driver is gone (a soft-deleted
     * driver loads as null, DCMP-07) or belongs to another company.
     */
    private function findAuthorizedDocument($documentId, string $ability): DriverComplianceDocument
    {
        $document = DriverComplianceDocument::with(['documentType', 'driver', 'file'])->whereKey($documentId)->first();
        abort_unless($document?->driver !== null, 404, 'Document not found');

        Gate::authorize($ability, $document->driver);

        return $document;
    }

    /**
     * Generate success message
     */
    private function generateSuccessMessage($uploadedCount, $updatedCount)
    {
        $messages = [];

        if ($uploadedCount > 0) {
            $messages[] = $uploadedCount === 1
                ? 'Document uploaded to 1 driver'
                : "Documents uploaded to {$uploadedCount} drivers";
        }

        if ($updatedCount > 0) {
            $messages[] = $updatedCount === 1
                ? '1 driver document updated'
                : "{$updatedCount} driver documents updated";
        }

        return implode(' and ', $messages);
    }
}
